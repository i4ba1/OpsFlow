<?php

namespace App\Actions\Comments;

use App\Enums\CommentVisibility;
use App\Events\CommentCreated;
use App\Events\CommentCreatedBroadcast;
use App\Models\Comment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\DB;

/**
 * Add a comment to a service request (PRD §14). INTERNAL comments require the
 * comment.internal capability, enforced by route middleware before this action
 * runs. The row, its participant notifications and the realtime push live in
 * one transaction; the CommentCreated domain event fires after commit.
 */
final readonly class CreateComment
{
    public function handle(
        ServiceRequest $serviceRequest,
        User $author,
        string $body,
        CommentVisibility $visibility = CommentVisibility::Public,
    ): Comment {
        return DB::transaction(function () use ($serviceRequest, $author, $body, $visibility): Comment {
            $comment = Comment::query()->create([
                'organization_id' => $serviceRequest->organization_id,
                'service_request_id' => $serviceRequest->id,
                'author_id' => $author->id,
                'body' => $body,
                'visibility' => $visibility->value,
            ]);

            $this->notifyParticipants($comment, $author, $serviceRequest);

            DB::afterCommit(function () use ($comment, $author): void {
                CommentCreated::dispatch($comment, $author);
                CommentCreatedBroadcast::dispatch($comment);
            });

            return $comment;
        });
    }

    /**
     * Notify the request assignee and requester when they are not the author.
     */
    private function notifyParticipants(Comment $comment, User $author, ServiceRequest $serviceRequest): void
    {
        $recipientIds = collect([$serviceRequest->assigned_to, $serviceRequest->requested_by])
            ->filter()
            ->unique()
            ->reject(fn (string $userId): bool => $userId === $author->id)
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        User::query()
            ->whereKey($recipientIds->all())
            ->each(function (User $recipient) use ($comment, $author, $serviceRequest): void {
                NotificationDispatcher::dispatch(
                    $recipient,
                    $author,
                    NotificationType::REQUEST_COMMENT_ADDED,
                    [
                        'service_request_id' => $serviceRequest->id,
                        'request_number' => $serviceRequest->request_number,
                        'comment_id' => $comment->id,
                        'title' => $serviceRequest->title,
                    ],
                    'service_request',
                    $serviceRequest->id,
                );
            });
    }
}
