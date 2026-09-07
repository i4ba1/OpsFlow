<?php

namespace App\Actions\ServiceRequests;

use App\Domain\SLA\SlaPolicyService;
use App\Enums\ServiceRequestEventType;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Events\ServiceRequestCreated as ServiceRequestCreatedEvent;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\RequestNumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Create a service request inside a single transaction (PRD §26): allocate the
 * concurrency-safe request number, insert the request in NEW state, apply the
 * SLA policy due dates when an active policy with a matching priority target
 * exists (PRD §13.2), record the creation event on the unified timeline and
 * dispatch ServiceRequestCreated after commit.
 */
final readonly class CreateServiceRequest
{
    public function __construct(
        private RequestNumberGenerator $numbers,
        private SlaPolicyService $slaPolicies,
    ) {}

    /**
     * @param  array{title: string, description: string, customer_id: string, priority?: string, project_id?: string|null, category_id?: string|null, sla_policy_id?: string|null}  $data
     */
    public function handle(User $actor, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($actor, $data): ServiceRequest {
            $serviceRequest = ServiceRequest::query()->create([
                'organization_id' => $actor->organization_id,
                'request_number' => $this->numbers->next($actor->organization_id),
                'customer_id' => $data['customer_id'],
                'project_id' => $data['project_id'] ?? null,
                'requested_by' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'category_id' => $data['category_id'] ?? null,
                'priority' => $data['priority'] ?? ServiceRequestPriority::Medium->value,
                'status' => ServiceRequestStatus::New->value,
                'sla_policy_id' => $data['sla_policy_id'] ?? null,
                'version' => 1,
            ]);

            // Apply the SLA policy (PRD §13.2): when the request references an
            // active policy with a target for its priority the due dates are
            // computed from business hours and persisted inside the same
            // transaction; otherwise they stay null.
            $this->slaPolicies->applyToRequest($serviceRequest);

            $serviceRequest->events()->create([
                'organization_id' => $actor->organization_id,
                'type' => ServiceRequestEventType::StatusChanged,
                'actor_id' => $actor->id,
                'from_value' => null,
                'to_value' => ServiceRequestStatus::New->value,
            ]);

            DB::afterCommit(
                fn () => ServiceRequestCreatedEvent::dispatch($serviceRequest->fresh(), $actor),
            );

            return $serviceRequest->fresh();
        });
    }
}
