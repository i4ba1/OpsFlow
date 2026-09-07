<?php

namespace App\Actions\ServiceRequests;

use App\Domain\ServiceRequests\ServiceRequestWorkflow;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Close a resolved service request. The workflow matrix requires the request
 * to be RESOLVED and the actor to hold request.close (enforced by route
 * middleware).
 */
final readonly class CloseServiceRequest
{
    public function __construct(
        private TransitionServiceRequest $transition,
        private ServiceRequestWorkflow $workflow,
    ) {}

    public function handle(ServiceRequest $serviceRequest, User $actor, ?string $note = null, ?int $expectedVersion = null): ServiceRequest
    {
        $this->workflow->assertCanTransition($serviceRequest->status, ServiceRequestStatus::Closed);

        return $this->transition->handle($serviceRequest, ServiceRequestStatus::Closed, $actor, $note, $expectedVersion);
    }
}
