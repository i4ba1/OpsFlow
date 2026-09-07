<?php

namespace App\Actions\ServiceRequests;

use App\Domain\ServiceRequests\ServiceRequestWorkflow;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Reopen a RESOLVED or CLOSED request back to IN_PROGRESS (manager only, PRD
 * §12.2). The actor must hold request.reopen, enforced by route middleware.
 */
final readonly class ReopenServiceRequest
{
    public function __construct(
        private TransitionServiceRequest $transition,
        private ServiceRequestWorkflow $workflow,
    ) {}

    public function handle(ServiceRequest $serviceRequest, User $actor, ?string $note = null, ?int $expectedVersion = null): ServiceRequest
    {
        $this->workflow->assertCanTransition($serviceRequest->status, ServiceRequestStatus::InProgress);

        return $this->transition->handle($serviceRequest, ServiceRequestStatus::InProgress, $actor, $note, $expectedVersion);
    }
}
