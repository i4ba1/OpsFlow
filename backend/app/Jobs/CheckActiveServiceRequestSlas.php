<?php

namespace App\Jobs;

use App\Domain\SLA\BusinessCalendar as SlaBusinessCalendar;
use App\Domain\SLA\SlaEventRecorder;
use App\Domain\SLA\SlaStateEvaluator;
use App\Enums\ServiceRequestStatus;
use App\Enums\SlaEventType;
use App\Enums\SlaState;
use App\Models\BusinessCalendar;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Notifications\NotificationType;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Per-minute SLA monitor (PRD §13.5). Scans requests that are still open,
 * carry an active policy with a resolution due date and have not been
 * resolved, derives their SLA state and records AT_RISK / BREACHED events plus
 * notifications exactly once per request + threshold (PRD §13.6). The unique
 * dedupe_key on sla_events plus withoutOverlapping() make the job idempotent;
 * the scheduler entry lives in routes/console.php.
 */
final class CheckActiveServiceRequestSlas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly SlaStateEvaluator $evaluator,
        private readonly SlaEventRecorder $recorder,
    ) {}

    /**
     * Horizon queue tags for this recurring job.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return ['sla', 'monitor'];
    }

    public function handle(): void
    {
        $now = CarbonImmutable::now();

        /** @var Collection<int, ServiceRequest> $candidates */
        $candidates = $this->candidates();

        if ($candidates->isEmpty()) {
            return;
        }

        $calendars = $this->loadCalendars($candidates);

        foreach ($candidates as $request) {
            $calendar = $request->business_calendar_id !== null
                ? ($calendars[$request->business_calendar_id] ?? null)
                : null;

            $this->process($request, $now, $calendar);
        }
    }

    /**
     * Open, unresolved requests whose active policy defines a resolution
     * target for their priority. Selected as a single query so the monitor
     * does not fetch full policy relations per request.
     *
     * @return Collection<int, ServiceRequest>
     */
    private function candidates(): Collection
    {
        $rows = ServiceRequest::query()
            ->select([
                'service_requests.id',
                'service_requests.organization_id',
                'service_requests.request_number',
                'service_requests.title',
                'service_requests.priority',
                'service_requests.created_at',
                'service_requests.resolution_due_at',
                'service_requests.resolved_at',
                'service_requests.assigned_to',
                'service_requests.requested_by',
                'sla_policies.id as policy_id',
                'sla_policies.timezone',
                'sla_policies.business_calendar_id',
                'sla_targets.resolution_minutes',
                'sla_targets.warning_threshold_percent',
            ])
            ->join('sla_policies', 'sla_policies.id', '=', 'service_requests.sla_policy_id')
            ->leftJoin('business_calendars', 'business_calendars.id', '=', 'sla_policies.business_calendar_id')
            ->join('sla_targets', function ($join): void {
                $join->on('sla_targets.sla_policy_id', '=', 'sla_policies.id')
                    ->on('sla_targets.priority', '=', 'service_requests.priority');
            })
            ->where('sla_policies.active', true)
            ->whereNull('service_requests.resolved_at')
            ->whereNotNull('service_requests.resolution_due_at')
            ->whereNotIn('service_requests.status', [
                ServiceRequestStatus::Resolved->value,
                ServiceRequestStatus::Closed->value,
                ServiceRequestStatus::Cancelled->value,
            ])
            ->orderBy('service_requests.id')
            ->get();

        /** @var Collection<int, ServiceRequest> $rows */
        return $rows;
    }

    /**
     * @param  Collection<int, ServiceRequest>  $candidates
     * @return array<string, SlaBusinessCalendar>
     */
    private function loadCalendars(Collection $candidates): array
    {
        $ids = $candidates->pluck('business_calendar_id')->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $calendars = [];

        BusinessCalendar::query()
            ->with('hours', 'holidays')
            ->whereKey($ids)
            ->get()
            ->each(function (BusinessCalendar $calendar) use (&$calendars): void {
                $calendars[$calendar->id] = SlaBusinessCalendar::fromModel($calendar);
            });

        return $calendars;
    }

    private function process(
        ServiceRequest $request,
        CarbonImmutable $now,
        ?SlaBusinessCalendar $calendar,
    ): void {
        $state = $this->evaluator->derive(
            $request,
            $now,
            terminal: null,
            resolutionMinutes: (int) $request->resolution_minutes,
            warningThresholdPercent: (int) $request->warning_threshold_percent,
            calendar: $calendar,
            timezone: (string) $request->timezone,
        );

        $type = match ($state) {
            SlaState::AtRisk => SlaEventType::AtRisk,
            SlaState::Breached => SlaEventType::Breached,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $event = $this->recorder->recordOnce(
            organizationId: (string) $request->organization_id,
            serviceRequestId: (string) $request->id,
            type: $type,
            slaPolicyId: (string) $request->policy_id,
            priority: $request->priority->value,
            thresholdPercent: (int) $request->warning_threshold_percent,
            dueAt: $request->resolution_due_at,
            metadata: ['source' => 'sla_monitor'],
        );

        // Idempotency: the row already existed for this request + threshold,
        // so the crossing was handled on an earlier run.
        if (! $event->wasRecentlyCreated) {
            return;
        }

        $this->notify($request, $state);
    }

    private function notify(ServiceRequest $request, SlaState $state): void
    {
        // A request always has a requester, so a recipient always exists even
        // before the request is assigned to an operator.
        $recipientId = $request->assigned_to ?? $request->requested_by;

        /** @var User $recipient */
        $recipient = User::query()->findOrFail($recipientId);

        NotificationDispatcher::dispatch(
            $recipient,
            null,
            $state === SlaState::AtRisk ? NotificationType::SLA_AT_RISK : NotificationType::SLA_BREACHED,
            [
                'service_request_id' => $request->id,
                'request_number' => $request->request_number,
                'title' => $request->title,
                'priority' => $request->priority->value,
                'due_at' => $request->resolution_due_at?->toIso8601String(),
            ],
            'service_request',
            (string) $request->id,
        );
    }
}
