<?php

namespace App\Events;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a comment has been persisted and its transaction committed
 * (PRD §14). Carries the persisted comment plus the actor who wrote it; the
 * CommentCreatedBroadcast pushes the realtime notification over the request
 * channel.
 */
final class CommentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Comment $comment,
        public readonly User $actor,
    ) {}
}
