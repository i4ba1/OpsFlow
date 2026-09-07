<?php

namespace App\Events;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a service request has been created and its transaction
 * committed (PRD §26). Listeners, notifications and broadcasts arrive in
 * Phase 4; for now the event only carries the persisted aggregate and actor.
 */
final class ServiceRequestCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ServiceRequest $serviceRequest,
        public readonly User $actor,
    ) {}
}
