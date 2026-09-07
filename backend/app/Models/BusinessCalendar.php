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
 * A business calendar (PRD §13.3) holds the weekly working hours and named
 * holidays used by the SLA calculator. All SLA math runs in the calendar's
 * timezone; stored timestamps stay UTC.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $timezone
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Organization $organization
 * @property Collection<int, BusinessCalendarHour> $hours
 * @property Collection<int, BusinessCalendarHoliday> $holidays
 * @property Collection<int, SlaPolicy> $slaPolicies
 *
 * @method static Builder|BusinessCalendar query()
 */
class BusinessCalendar extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'timezone',
        'description',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<BusinessCalendarHour, $this> */
    public function hours(): HasMany
    {
        return $this->hasMany(BusinessCalendarHour::class);
    }

    /** @return HasMany<BusinessCalendarHoliday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(BusinessCalendarHoliday::class);
    }

    /** @return HasMany<SlaPolicy, $this> */
    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }
}
