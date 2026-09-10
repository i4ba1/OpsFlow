<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $email
 * @property UserStatus $status
 * @property string $timezone
 * @property string|null $password
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static UserFactory factory(...$parameters)
 * @method static Builder|User query()
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'status',
        'timezone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @return HasMany<CustomerContact, $this> */
    public function customerContacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function hasPermission(string $permission): bool
    {
        $organization = $this->organization;

        if ($organization !== null && $organization->is_suspended) {
            return false;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->whereKey($permission))
            ->exists();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
