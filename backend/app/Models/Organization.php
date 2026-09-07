<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $legal_name
 * @property string|null $registration_number
 * @property string $country_code
 * @property string $timezone
 * @property string $currency
 * @property string|null $logo_path
 * @property OrganizationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\OrganizationFactory factory(...$parameters)
 * @method static Builder|Organization query()
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'registration_number',
        'country_code',
        'timezone',
        'currency',
        'logo_path',
        'status',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
        ];
    }

    public function getIsSuspendedAttribute(): bool
    {
        return $this->status === OrganizationStatus::Suspended;
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OrganizationStatus::Active);
    }
}
