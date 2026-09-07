<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime "request.comment.created" event (PRD §34). Broadcast on the private
 * request channel so open request views refresh their comment thread.
 * Dispatched inside DB::afterCommit by CreateComment.
 */
final class CommentCreatedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Comment $comment,
    ) {}

    public function broadcastAs(): string
    {
        return 'request.comment.created';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('private-request.'.$this->comment->service_request_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'resource_type' => 'comment',
            'resource_id' => $this->comment->id,
            'service_request_id' => $this->comment->service_request_id,
        ];
    }
}
