<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An SLA policy (PRD §13.2): a named set of per-priority targets evaluated in
 * the policy timezone, optionally against a business calendar. Only active
 * policies are applied when requests are created or reprioritized.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property string $timezone
 * @property string|null $business_calendar_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Organization $organization
 * @property BusinessCalendar|null $businessCalendar
 * @property Collection<int, SlaTarget> $targets
 *
 * @method static Builder|SlaPolicy query()
 */
class SlaPolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'timezone',
        'business_calendar_id',
        'active',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<BusinessCalendar, $this> */
    public function businessCalendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class);
    }

    /** @return HasMany<SlaTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(SlaTarget::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
