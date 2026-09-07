<?php

namespace App\Events;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a service request status transition has committed (PRD
 * §26/§31). Listeners, notifications and broadcasts arrive in Phase 4.
 */
final class ServiceRequestStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ServiceRequest $serviceRequest,
        public readonly ServiceRequestStatus $from,
        public readonly ServiceRequestStatus $to,
        public readonly User $actor,
    ) {}
}
