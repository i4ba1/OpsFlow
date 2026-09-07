<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Update the body of an own comment (PRD §14). Ownership and the
 * comment.update_own capability are enforced by route middleware before this
 * action runs; the action itself only guards soft-deleted rows.
 */
final readonly class UpdateComment
{
    public function handle(Comment $comment, User $author, string $body): Comment
    {
        return DB::transaction(function () use ($comment, $author, $body): Comment {
            $locked = Comment::query()
                ->whereKey($comment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->trashed()) {
                abort(404, 'This comment has been deleted.');
            }

            if ($locked->author_id !== $author->id) {
                abort(403, 'Only the author can edit this comment.');
            }

            $locked->update(['body' => $body]);

            return $locked->fresh();
        });
    }
}
