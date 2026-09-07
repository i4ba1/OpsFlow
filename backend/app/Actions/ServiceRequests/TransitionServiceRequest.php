<?php

namespace App\Actions\ServiceRequests;

use App\Domain\ServiceRequests\ServiceRequestWorkflow;
use App\Enums\ServiceRequestEventType;
use App\Enums\ServiceRequestStatus;
use App\Events\ServiceRequestStatusChanged as ServiceRequestStatusChangedEvent;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\ServiceRequestVersionConflictException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transition a service request to a target status (PRD §12.3, §26, §29).
 *
 * The request row is locked, the transition is validated against the state
 * machine, the current version supplied by the client is checked, the status
 * and its derived timestamps (resolved_at / closed_at) are updated, the
 * timeline row is inserted and ServiceRequestStatusChanged is dispatched after
 * commit.
 */
final readonly class TransitionServiceRequest
{
    public function __construct(
        private ServiceRequestWorkflow $workflow,
    ) {}

    /**
     * @param  int|null  $expectedVersion  version observed by the client; when
     *                                     provided it must match the persisted
     *                                     version or a 409 conflict is raised
     *
     * @throws InvalidTransitionException
     * @throws ServiceRequestVersionConflictException
     */
    public function handle(
        ServiceRequest $serviceRequest,
        ServiceRequestStatus $target,
        User $actor,
        ?string $note = null,
        ?int $expectedVersion = null,
    ): ServiceRequest {
        return DB::transaction(function () use ($serviceRequest, $target, $actor, $note, $expectedVersion): ServiceRequest {
            $locked = ServiceRequest::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersion($expectedVersion, (int) $locked->version);

            $from = $locked->status;

            $this->workflow->assertCanTransition($from, $target);

            $now = now();

            $locked->status = $target;
            $locked->version++;
            // The first internal handling of the request is recorded when it
            // is triaged for the first time; it is never cleared.
            if (
                $from === ServiceRequestStatus::New
                && $target === ServiceRequestStatus::Triaged
                && $locked->first_responded_at === null
            ) {
                $locked->first_responded_at = $now;
            }
            $locked->resolved_at = $this->resolvedAt($locked->resolved_at, $from, $target, $now);
            $locked->closed_at = $this->closedAt($locked->closed_at, $from, $target, $now);
            $locked->save();

            $locked->events()->create([
                'organization_id' => $locked->organization_id,
                'type' => ServiceRequestEventType::StatusChanged,
                'actor_id' => $actor->id,
                'from_value' => $from->value,
                'to_value' => $target->value,
                'note' => $note,
            ]);

            DB::afterCommit(
                fn () => ServiceRequestStatusChangedEvent::dispatch(
                    $locked->fresh(),
                    $from,
                    $target,
                    $actor,
                )
            );

            return $locked->fresh();
        });
    }

    private function assertVersion(?int $expectedVersion, int $currentVersion): void
    {
        if ($expectedVersion !== null && $expectedVersion !== $currentVersion) {
            throw new ServiceRequestVersionConflictException;
        }
    }

    /**
     * resolved_at is set when entering RESOLVED and cleared when a resolved
     * request is reopened.
     */
    private function resolvedAt(?Carbon $resolvedAt, ServiceRequestStatus $from, ServiceRequestStatus $target, Carbon $now): ?Carbon
    {
        if ($target === ServiceRequestStatus::Resolved) {
            return $resolvedAt ?? $now;
        }

        if ($from === ServiceRequestStatus::Resolved && $target === ServiceRequestStatus::InProgress) {
            return null;
        }

        return $resolvedAt;
    }

    private function closedAt(?Carbon $closedAt, ServiceRequestStatus $from, ServiceRequestStatus $target, Carbon $now): ?Carbon
    {
        if ($target === ServiceRequestStatus::Closed) {
            return $closedAt ?? $now;
        }

        if ($from === ServiceRequestStatus::Closed && $target === ServiceRequestStatus::InProgress) {
            return null;
        }

        return $closedAt;
    }
}
