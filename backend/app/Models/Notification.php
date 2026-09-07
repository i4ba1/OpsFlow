<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An in-app notification row (PRD §18). Created by the NotificationDispatcher
 * rather than through Laravel's mail/queue notification pipeline; delivery
 * channels are added in Phase 9. read_at null means unread.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $type
 * @property array<string, mixed>|null $data
 * @property Carbon|null $read_at
 * @property string|null $actor_id
 * @property string|null $resource_type
 * @property string|null $resource_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User $user
 * @property User|null $actor
 * @property Organization $organization
 *
 * @method static Builder|Notification query()
 */
class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'type',
        'data',
        'read_at',
        'actor_id',
        'resource_type',
        'resource_id',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * The notification is still unread.
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->forceFill(['read_at' => $this->read_at ?? now()])->save();
    }

    /**
     * Unread notifications first, newest on top.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeOrderForInbox(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at');
    }
}
