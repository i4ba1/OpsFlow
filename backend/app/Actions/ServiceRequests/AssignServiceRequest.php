<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestEventType;
use App\Events\RequestAssignedBroadcast;
use App\Exceptions\RequestNotAssignableException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\DB;

/**
 * Assign a service request to an organization user (PRD §8.1). The request is
 * locked for the duration of the transaction, assignment is refused for
 * terminal states, and the assignment is recorded on the unified timeline.
 * After commit the assignee is notified (in-app row + realtime request.assigned).
 */
final readonly class AssignServiceRequest
{
    /**
     * @throws RequestNotAssignableException
     */
    public function handle(ServiceRequest $serviceRequest, User $assignee, User $actor, ?string $note = null): ServiceRequest
    {
        return DB::transaction(function () use ($serviceRequest, $assignee, $actor, $note): ServiceRequest {
            $locked = ServiceRequest::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isAssignable()) {
                throw new RequestNotAssignableException;
            }

            $locked->assigned_to = $assignee->id;
            $locked->version++;
            $locked->save();

            $locked->events()->create([
                'organization_id' => $locked->organization_id,
                'type' => ServiceRequestEventType::Assigned,
                'actor_id' => $actor->id,
                'to_value' => $assignee->id,
                'note' => $note,
                'metadata' => ['assignee' => ['id' => $assignee->id, 'name' => $assignee->name]],
            ]);

            $fresh = $locked->fresh();

            // The assignee is notified when the assignment commits: the row is
            // written in this transaction, the realtime event is deferred to
            // after commit by ShouldBroadcastAfterCommit.
            if ($assignee->id !== $actor->id) {
                NotificationDispatcher::dispatch(
                    $assignee,
                    $actor,
                    NotificationType::REQUEST_ASSIGNED,
                    [
                        'service_request_id' => $fresh->id,
                        'request_number' => $fresh->request_number,
                        'title' => $fresh->title,
                    ],
                    'service_request',
                    $fresh->id,
                );
            }

            DB::afterCommit(fn () => RequestAssignedBroadcast::dispatch($fresh, $assignee));

            return $fresh;
        });
    }
}
