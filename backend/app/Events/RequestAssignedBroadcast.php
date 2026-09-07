<?php

namespace App\Events;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime "request.assigned" event (PRD §34). Broadcast on the private request
 * channel carrying the assignee id; clients reconcile against the persisted
 * request state. Dispatched inside DB::afterCommit by AssignServiceRequest.
 */
final class RequestAssignedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ServiceRequest $serviceRequest,
        public readonly User $assignee,
    ) {}

    public function broadcastAs(): string
    {
        return 'request.assigned';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('private-request.'.$this->serviceRequest->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'resource_type' => 'service_request',
            'resource_id' => $this->serviceRequest->id,
            'assignee_id' => $this->assignee->id,
        ];
    }
}
