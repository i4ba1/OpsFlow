<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestEventType;
use App\Enums\ServiceRequestPriority;
use App\Exceptions\RequestNotEditableException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change the priority of a service request. Terminal requests are immutable,
 * so RESOLVED / CLOSED / CANCELLED requests are rejected. The change is
 * recorded on the unified timeline with the previous and new values.
 */
final readonly class ChangeServiceRequestPriority
{
    /**
     * @throws RequestNotEditableException
     */
    public function handle(ServiceRequest $serviceRequest, ServiceRequestPriority $priority, User $actor): ServiceRequest
    {
        return DB::transaction(function () use ($serviceRequest, $priority, $actor): ServiceRequest {
            $locked = ServiceRequest::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isEditable()) {
                throw new RequestNotEditableException;
            }

            $from = $locked->priority;

            $locked->priority = $priority;
            $locked->version++;
            $locked->save();

            $locked->events()->create([
                'organization_id' => $locked->organization_id,
                'type' => ServiceRequestEventType::PriorityChanged,
                'actor_id' => $actor->id,
                'from_value' => $from->value,
                'to_value' => $priority->value,
            ]);

            return $locked->fresh();
        });
    }
}
