<?php

namespace App\Actions\ServiceRequests;

use App\Domain\SLA\SlaPolicyService;
use App\Enums\ServiceRequestEventType;
use App\Enums\ServiceRequestPriority;
use App\Exceptions\RequestNotEditableException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change the priority of a service request. Terminal requests are immutable,
 * so RESOLVED / CLOSED / CANCELLED requests are rejected. The change is
 * recorded on the unified timeline with the previous and new values. When the
 * request carries an active SLA policy the due dates are recomputed from the
 * new priority's target (PRD §13.2); a policy with no target for the new
 * priority clears them.
 */
final readonly class ChangeServiceRequestPriority
{
    public function __construct(
        private SlaPolicyService $slaPolicies,
    ) {}

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

            // Recompute the SLA due dates from the new priority when the
            // request has an active policy with a matching target.
            $locked->loadMissing(['policy.targets', 'policy.businessCalendar']);
            $this->slaPolicies->applyToRequest($locked);

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
