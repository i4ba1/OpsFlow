<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime "notification.created" event (PRD §34). Broadcast on the
 * recipient's private-user channel so the client can bump its unread badge and
 * fetch the notification. Dispatched inside DB::afterCommit by the
 * NotificationDispatcher, so recipients never see broadcasts for rolled-back
 * work.
 */
final class NotificationCreatedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Notification $notification,
    ) {}

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('private-user.'.$this->notification->user_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'resource_type' => 'notification',
            'resource_id' => $this->notification->id,
            'type' => $this->notification->type,
        ];
    }
}
