<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime "request.updated" event (PRD §34). Broadcast on the private request
 * channel with only the resource identity — the database stays the source of
 * truth and clients re-fetch state. Callers dispatch inside DB::afterCommit so
 * the broadcast only goes out once the underlying change has committed.
 */
final class RequestUpdatedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ServiceRequest $serviceRequest,
    ) {}

    /**
     * The name clients subscribe to (PRD §34): request.updated.
     */
    public function broadcastAs(): string
    {
        return 'request.updated';
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
        ];
    }
}
