<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Soft-delete a comment (PRD §14). Ownership and the comment.delete_own
 * capability are enforced by route middleware; deleted comments disappear from
 * listings while their history is retained.
 */
final readonly class DeleteComment
{
    public function handle(Comment $comment, User $author): void
    {
        DB::transaction(function () use ($comment, $author): void {
            $locked = Comment::query()
                ->whereKey($comment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->trashed()) {
                return;
            }

            if ($locked->author_id !== $author->id) {
                abort(403, 'Only the author can delete this comment.');
            }

            $locked->delete();
        });
    }
}
