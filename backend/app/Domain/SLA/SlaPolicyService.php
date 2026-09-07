<?php

namespace App\Domain\SLA;

use App\Enums\ServiceRequestPriority;
use App\Models\BusinessCalendar as BusinessCalendarModel;
use App\Models\ServiceRequest;
use App\Models\SlaPolicy;
use App\Models\SlaTarget;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Application service that applies an SLA policy to a service request (PRD
 * §13.2/§26): it resolves the priority's target row, walks the policy's
 * business calendar and persists the first-response/resolution due dates on
 * the request. Due dates are stored in UTC.
 */
final class SlaPolicyService
{
    public function __construct(
        private readonly SlaCalculator $calculator,
    ) {}

    /**
     * Compute and persist the SLA due dates of a request from its (active)
     * policy, priority and business calendar. When the request has no policy,
     * the policy is inactive, the priority has no target, or the policy's
     * calendar holds no working hours, the due dates are cleared.
     */
    public function applyToRequest(ServiceRequest $serviceRequest): void
    {
        $target = $this->targetFor($serviceRequest);

        if ($target === null) {
            $this->clearDueDates($serviceRequest);

            return;
        }

        $this->computeForTarget($serviceRequest, $target);
    }

    /**
     * The due dates (UTC) that would be applied for a request with the given
     * priority and policy, measured from now. Used to recompute due dates when
     * a request's priority changes.
     *
     * @return array{first_response_due_at: CarbonImmutable|null, resolution_due_at: CarbonImmutable|null}
     */
    public function recomputeDueDates(SlaPolicy $policy, ServiceRequestPriority $priority, CarbonImmutable $start): array
    {
        $target = $policy->targets
            ->first(fn (SlaTarget $candidate): bool => $candidate->priority === $priority->value);

        if ($target === null || ! $policy->active) {
            return ['first_response_due_at' => null, 'resolution_due_at' => null];
        }

        $calendar = $this->calendarFor($policy);

        if ($calendar === null) {
            return ['first_response_due_at' => null, 'resolution_due_at' => null];
        }

        return [
            'first_response_due_at' => $this->calculator->dueAt(
                $start,
                (int) $target->first_response_minutes,
                $calendar,
                $policy->timezone,
            ),
            'resolution_due_at' => $this->calculator->dueAt(
                $start,
                (int) $target->resolution_minutes,
                $calendar,
                $policy->timezone,
            ),
        ];
    }

    /**
     * The matching target row for a request's priority, or null when the
     * request has no policy or the policy is inactive.
     */
    public function targetFor(ServiceRequest $serviceRequest): ?SlaTarget
    {
        if ($serviceRequest->sla_policy_id === null) {
            return null;
        }

        $policy = $serviceRequest->policy;

        if ($policy === null || ! $policy->active) {
            return null;
        }

        $priority = $serviceRequest->priority->value;

        return $policy->targets
            ->first(fn (SlaTarget $candidate): bool => $candidate->priority === $priority);
    }

    private function calendarFor(SlaPolicy $policy): ?BusinessCalendar
    {
        if ($policy->business_calendar_id === null) {
            return null;
        }

        /** @var BusinessCalendarModel|null $model */
        $model = $policy->businessCalendar;

        if ($model === null) {
            return null;
        }

        return BusinessCalendar::fromModel($model);
    }

    private function computeForTarget(ServiceRequest $serviceRequest, SlaTarget $target): void
    {
        $policy = $serviceRequest->policy;

        if ($policy === null || $policy->business_calendar_id === null) {
            throw new RuntimeException('SLA target resolved without an active policy calendar.');
        }

        $calendar = $this->calendarFor($policy);

        if ($calendar === null) {
            $this->clearDueDates($serviceRequest);

            return;
        }

        $start = CarbonImmutable::now();

        $serviceRequest->forceFill([
            'first_response_due_at' => $this->calculator->dueAt(
                $start,
                (int) $target->first_response_minutes,
                $calendar,
                $policy->timezone,
            ),
            'resolution_due_at' => $this->calculator->dueAt(
                $start,
                (int) $target->resolution_minutes,
                $calendar,
                $policy->timezone,
            ),
        ])->save();
    }

    private function clearDueDates(ServiceRequest $serviceRequest): void
    {
        if ($serviceRequest->first_response_due_at === null && $serviceRequest->resolution_due_at === null) {
            return;
        }

        $serviceRequest->forceFill([
            'first_response_due_at' => null,
            'resolution_due_at' => null,
        ])->save();
    }
}
