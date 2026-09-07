<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One working interval of a business calendar for a day of the week
 * (0 = Sunday .. 6 = Saturday). A day without a row is closed.
 *
 * @property string $id
 * @property string $business_calendar_id
 * @property int $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property BusinessCalendar $businessCalendar
 *
 * @method static Builder|BusinessCalendarHour query()
 */
class BusinessCalendarHour extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_calendar_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    /** @return BelongsTo<BusinessCalendar, $this> */
    public function businessCalendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class);
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }
}
