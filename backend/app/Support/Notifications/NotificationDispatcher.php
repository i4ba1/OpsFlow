<?php

namespace App\Support\Notifications;

use App\Events\NotificationCreatedBroadcast;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Persists in-app notification rows (PRD §18) and pushes the realtime
 * notification.created event. Laravel's mail/queue notification pipeline and
 * the external delivery channels land in Phase 9; today the row itself is the
 * delivery.
 *
 * Callers that change state run inside a DB transaction and invoke this
 * dispatcher within it, so the notification row commits atomically with the
 * change that produced it (no notifications for rolled-back work, none lost).
 * NotificationCreatedBroadcast implements ShouldBroadcastAfterCommit, so the
 * realtime push still waits for the surrounding transaction to commit.
 */
final class NotificationDispatcher
{
    /**
     * Create a notification for a single recipient and broadcast it.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatch(
        User $recipient,
        ?User $actor,
        string $type,
        array $payload = [],
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): ?Notification {
        $organizationId = $actor !== null ? $actor->organization_id : $recipient->organization_id;

        if ($recipient->organization_id !== $organizationId) {
            return null;
        }

        $notification = Notification::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $recipient->id,
            'type' => $type,
            'data' => $payload === [] ? null : $payload,
            'actor_id' => $actor?->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        // The realtime push happens after the surrounding transaction commits;
        // when no transaction is active the callback runs immediately.
        DB::afterCommit(fn () => NotificationCreatedBroadcast::dispatch($notification));

        return $notification;
    }
}
