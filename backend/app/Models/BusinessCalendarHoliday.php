<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A named whole-day closure of a business calendar, matched in the calendar's
 * timezone.
 *
 * @property string $id
 * @property string $business_calendar_id
 * @property string $name
 * @property Carbon $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property BusinessCalendar $businessCalendar
 *
 * @method static Builder|BusinessCalendarHoliday query()
 */
class BusinessCalendarHoliday extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_calendar_id',
        'name',
        'date',
    ];

    /** @return BelongsTo<BusinessCalendar, $this> */
    public function businessCalendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
