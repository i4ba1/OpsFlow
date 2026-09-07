<?php

namespace App\Actions\ServiceRequests;

use App\Domain\ServiceRequests\ServiceRequestWorkflow;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Resolve a service request. The workflow matrix requires the request to be in
 * IN_PROGRESS or ESCALATED and the actor to hold request.resolve, enforced by
 * route middleware; the action itself validates the state machine.
 */
final readonly class ResolveServiceRequest
{
    public function __construct(
        private TransitionServiceRequest $transition,
        private ServiceRequestWorkflow $workflow,
    ) {}

    public function handle(ServiceRequest $serviceRequest, User $actor, ?string $note = null, ?int $expectedVersion = null): ServiceRequest
    {
        $this->workflow->assertCanTransition($serviceRequest->status, ServiceRequestStatus::Resolved);

        return $this->transition->handle($serviceRequest, ServiceRequestStatus::Resolved, $actor, $note, $expectedVersion);
    }
}
