<?php

namespace App\Domain\SLA;

use App\Models\BusinessCalendarHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Immutable working-hours snapshot of a business calendar (PRD §13.3). The
 * model row is read once and flattened into hour ranges plus a holiday set so
 * the SLA calculator stays a pure service with no query side effects. Hours
 * are keyed by day of week (0 = Sunday .. 6 = Saturday); a day without a range
 * is closed.
 *
 * @phpstan-type HourRange array{0: int, 1: int}
 */
final class BusinessCalendar
{
    /** @var array<int, HourRange> */
    private array $hours;

    /** @var array<string, true> normalized Y-m-d holiday dates */
    private array $holidays;

    /**
     * @param  iterable<int, BusinessCalendarHour>  $hours  rows to flatten
     * @param  iterable<string>  $holidayDates  normalized Y-m-d holiday dates
     *                                          in the calendar timezone
     */
    public function __construct(
        private readonly string $timezone,
        iterable $hours = [],
        iterable $holidayDates = [],
    ) {
        $this->hours = self::normalizeHours($hours);
        $this->holidays = [];

        foreach ($holidayDates as $date) {
            $this->holidays[$date] = true;
        }
    }

    /**
     * Build a snapshot from a persisted calendar model, eagerly loading its
     * hours and holidays.
     */
    public static function fromModel(\App\Models\BusinessCalendar $model): self
    {
        if (! $model->relationLoaded('hours')) {
            $model->load('hours', 'holidays');
        }

        $holidays = [];

        foreach ($model->holidays as $holiday) {
            $holidays[] = $holiday->date->format('Y-m-d');
        }

        return new self(
            timezone: $model->timezone,
            hours: $model->hours,
            holidayDates: $holidays,
        );
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * Is the instant (already expressed in the calendar timezone) inside a
     * working window and not on a holiday?
     */
    public function isBusinessTime(CarbonInterface $local): bool
    {
        return $this->rangeContaining($local) !== null;
    }

    /**
     * The working window containing the instant, in minutes since midnight in
     * the calendar timezone, or null when closed or on holiday.
     *
     * @return HourRange|null
     */
    public function rangeContaining(CarbonInterface $local): ?array
    {
        if ($this->isHoliday($local)) {
            return null;
        }

        return $this->rangeFor($local);
    }

    /**
     * The next opening window at or strictly after the instant, in minutes
     * since midnight. When the instant already sits on a working day before
     * its opening (e.g. 08:30 on a 09:00-18:00 day) that same day's opening is
     * returned. Returns null when the calendar has no weekly hours at all.
     *
     * @return array{day: CarbonImmutable, open_minute: int, close_minute: int}|null
     */
    public function nextOpening(CarbonInterface $local): ?array
    {
        // Same working day, before opening: jump to that day's opening.
        $todayRanges = $this->rangesForDay($local->dayOfWeek);

        if ($todayRanges !== null && ! $this->isHoliday($local)) {
            [$openToday, $closeToday] = $todayRanges;
            $minute = (int) $local->format('G') * 60 + (int) $local->format('i');

            if ($minute < $openToday) {
                return [
                    'day' => CarbonImmutable::instance($local)->startOfDay(),
                    'open_minute' => $openToday,
                    'close_minute' => $closeToday,
                ];
            }
        }

        $cursor = CarbonImmutable::instance($local)->startOfDay()->addDay();

        for ($day = 0; $day < 366; $day++) {
            $ranges = $this->rangesForDay($cursor->dayOfWeek);

            if ($ranges === null) {
                $cursor = $cursor->addDay();

                continue;
            }

            if ($this->isHoliday($cursor)) {
                $cursor = $cursor->addDay();

                continue;
            }

            [$openMinute] = $ranges;

            return [
                'day' => $cursor->startOfDay(),
                'open_minute' => $openMinute,
                'close_minute' => $ranges[1],
            ];
        }

        return null;
    }

    /**
     * Whether the timezone-local date is a named holiday.
     */
    public function isHoliday(CarbonInterface $local): bool
    {
        return isset($this->holidays[$local->format('Y-m-d')]);
    }

    /**
     * The day-of-week range containing the instant, in minutes since midnight.
     *
     * @return HourRange|null
     */
    private function rangeFor(CarbonInterface $local): ?array
    {
        $ranges = $this->rangesForDay($local->dayOfWeek);

        if ($ranges === null) {
            return null;
        }

        [$open, $close] = $ranges;
        $minute = (int) $local->format('G') * 60 + (int) $local->format('i');

        return $minute >= $open && $minute < $close ? $ranges : null;
    }

    /**
     * @return HourRange|null
     */
    private function rangesForDay(int $dayOfWeek): ?array
    {
        return $this->hours[$dayOfWeek] ?? null;
    }

    /**
     * @param  Collection<int, BusinessCalendarHour>  $hours
     * @return array<int, HourRange>
     */
    private static function normalizeHours(Collection $hours): array
    {
        $normalized = [];

        foreach ($hours as $hour) {
            $open = self::timeToMinutes($hour->opens_at);
            $close = self::timeToMinutes($hour->closes_at);

            // An interval that closes at or before it opens is not usable.
            if ($close <= $open) {
                continue;
            }

            $normalized[$hour->day_of_week] = [$open, $close];
        }

        return $normalized;
    }

    private static function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
