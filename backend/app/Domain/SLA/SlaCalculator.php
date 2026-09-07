<?php

namespace App\Domain\SLA;

use Carbon\CarbonImmutable;

/**
 * Pure SLA due-date arithmetic (PRD §68/§69).
 *
 * Every public method accepts an instant (usually UTC) and the calendar whose
 * timezone defines business hours, converts to the calendar timezone, walks
 * the working windows and returns UTC instants. The class is a pure service:
 * no HTTP, no model queries, fully unit-testable with CarbonImmutable.
 */
final class SlaCalculator
{
    /**
     * Advance a start instant by a number of business minutes. Non-working
     * time (outside hours, weekends, holidays) does not consume the budget: a
     * remaining minute at the end of a window rolls over to the next opening.
     */
    public function addBusinessMinutes(
        CarbonImmutable $start,
        int $minutes,
        BusinessCalendar $calendar,
        string $timezone,
    ): CarbonImmutable {
        if ($minutes < 0) {
            throw new \InvalidArgumentException('SLA duration minutes must not be negative.');
        }

        if ($minutes === 0) {
            return $start->toImmutable();
        }

        $cursor = $start->setTimezone($timezone);
        $remaining = $minutes;

        while ($remaining > 0) {
            $range = $calendar->rangeContaining($cursor);

            if ($range === null) {
                $cursor = $this->nextOpeningInstant($cursor, $calendar);

                continue;
            }

            [$openMinute, $closeMinute] = $range;
            $position = self::minutesSinceMidnight($cursor);

            if ($position >= $closeMinute) {
                $cursor = $this->nextOpeningInstant($cursor, $calendar);

                continue;
            }

            $available = $closeMinute - max($position, $openMinute);
            $consumed = min($available, $remaining);

            $cursor = $cursor->addMinutes($consumed);
            $remaining -= $consumed;
        }

        return $cursor->setTimezone('UTC');
    }

    /**
     * The UTC instant by which a duration (business minutes) measured from
     * $start must complete.
     */
    public function dueAt(
        CarbonImmutable $start,
        int $durationMinutes,
        BusinessCalendar $calendar,
        string $timezone,
    ): CarbonImmutable {
        return $this->addBusinessMinutes($start, $durationMinutes, $calendar, $timezone);
    }

    /**
     * Business minutes actually elapsed between two instants. Time before the
     * calendar's opening instant (e.g. requests created at night) does not
     * count; an instant exactly on an opening boundary starts the clock.
     */
    public function elapsedBusinessMinutes(
        CarbonImmutable $start,
        CarbonImmutable $now,
        BusinessCalendar $calendar,
        string $timezone,
    ): int {
        $cursor = $start->setTimezone($timezone);
        $end = $now->setTimezone($timezone);

        if (! $end->greaterThan($cursor)) {
            return 0;
        }

        $cursor = $this->nextOpeningInstant($cursor, $calendar);
        $elapsed = 0;

        while ($cursor->lessThan($end)) {
            $range = $calendar->rangeContaining($cursor);

            if ($range === null) {
                $cursor = $this->nextOpeningInstant($cursor, $calendar);

                continue;
            }

            [, $closeMinute] = $range;
            $closing = $cursor->copy()->startOfDay()->addMinutes($closeMinute);

            if ($closing->greaterThan($end)) {
                $elapsed += (int) floor($cursor->diffInSeconds($end) / 60);
                $cursor = $end;
                break;
            }

            $elapsed += (int) floor($cursor->diffInSeconds($closing) / 60);
            $cursor = $this->nextOpeningInstant($closing, $calendar);
        }

        return $elapsed;
    }

    /**
     * Snap an instant (calendar-local) forward to the next opening boundary:
     * if it already sits inside a working window it is returned unchanged;
     * otherwise it jumps to the start of the next working window.
     */
    private function nextOpeningInstant(CarbonImmutable $cursor, BusinessCalendar $calendar): CarbonImmutable
    {
        $range = $calendar->rangeContaining($cursor);

        if ($range !== null && self::minutesSinceMidnight($cursor) >= $range[0]) {
            return $cursor;
        }

        $next = $calendar->nextOpening($cursor);

        if ($next === null) {
            return $cursor;
        }

        return $next['day']->addMinutes($next['open_minute']);
    }

    private static function minutesSinceMidnight(CarbonImmutable $instant): int
    {
        return (int) $instant->format('G') * 60 + (int) $instant->format('i');
    }
}
