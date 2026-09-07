<?php

use App\Domain\SLA\BusinessCalendar;
use App\Domain\SLA\SlaCalculator;
use Carbon\CarbonImmutable;

/**
 * The default OpsFlow working week (PRD §13.3): Mon-Fri 09:00-18:00 in
 * Asia/Singapore (UTC+8, no DST). 2026-09-04 is a Friday, 2026-09-05/06 the
 * weekend, 2026-09-07 the following Monday and 2026-09-08 the Tuesday after.
 * Starts are stored in UTC and the calculator converts into the calendar
 * timezone before walking windows (§68-69).
 */
function slaCalendar(array $holidays = []): BusinessCalendar
{
    $hours = collect([1, 2, 3, 4, 5])->map(fn (int $day): object => (object) [
        'day_of_week' => $day,
        'opens_at' => '09:00:00',
        'closes_at' => '18:00:00',
    ]);

    return new BusinessCalendar('Asia/Singapore', $hours, $holidays);
}

it('adds business minutes across a weekend: Fri 17:00 UTC + 60m lands Mon 10:00 SGT', function () {
    // Fri 17:00 UTC is already Saturday 01:00 in Singapore, so the whole hour
    // is consumed on Monday 09:00-10:00 SGT (PRD §68 weekend rollover).
    $start = CarbonImmutable::parse('2026-09-04 17:00:00', 'UTC');

    $due = (new SlaCalculator)->addBusinessMinutes($start, 60, slaCalendar(), 'Asia/Singapore');

    expect($due->timezoneName)->toBe('UTC');
    expect($due->toDateTimeString())->toBe('2026-09-07 02:00:00'); // Mon 10:00 SGT
});

it('consumes the remaining window minutes before rolling to the next day', function () {
    // Fri 17:30 SGT (09:30 UTC): 30 minutes left of the window. A 120-minute
    // budget consumes those 30, then 90 more on Monday 09:00-10:30.
    $start = CarbonImmutable::parse('2026-09-04 09:30:00', 'UTC');

    $due = (new SlaCalculator)->addBusinessMinutes($start, 120, slaCalendar(), 'Asia/Singapore');

    expect($due->toDateTimeString())->toBe('2026-09-07 02:30:00'); // Mon 10:30 SGT
});

it('crossing midnight inside the working week keeps counting the next morning', function () {
    // Thu 17:30 SGT (09:30 UTC): 30 minutes to close, then Friday 09:00-10:30.
    $start = CarbonImmutable::parse('2026-09-03 09:30:00', 'UTC');

    $due = (new SlaCalculator)->addBusinessMinutes($start, 120, slaCalendar(), 'Asia/Singapore');

    expect($due->toDateTimeString())->toBe('2026-09-04 02:30:00'); // Fri 10:30 SGT
});

it('starts at the next opening when the request lands outside working hours', function () {
    // Sunday 2026-09-06 22:00 SGT (14:00 UTC) is closed: a 30-minute budget is
    // consumed entirely at Monday 09:00-09:30 SGT.
    $start = CarbonImmutable::parse('2026-09-06 14:00:00', 'UTC');

    $due = (new SlaCalculator)->addBusinessMinutes($start, 30, slaCalendar(), 'Asia/Singapore');

    expect($due->toDateTimeString())->toBe('2026-09-07 01:30:00'); // Mon 09:30 SGT
});

it('skips holidays entirely', function () {
    // Monday 2026-09-07 is a holiday: Saturday's hour of budget rolls past it
    // to Tuesday 09:00-10:00 SGT.
    $calendar = slaCalendar(['2026-09-07']);

    $start = CarbonImmutable::parse('2026-09-05 17:00:00', 'UTC'); // Sun 01:00 SGT

    $due = (new SlaCalculator)->addBusinessMinutes($start, 60, $calendar, 'Asia/Singapore');

    expect($due->toDateTimeString())->toBe('2026-09-08 02:00:00'); // Tue 10:00 SGT
});

it('maps a UTC start into the calendar timezone before walking windows', function () {
    // 2026-09-04 00:30 UTC is 08:30 SGT on Friday — before opening. The hour
    // budget starts counting at 09:00 SGT and lands on 10:00 SGT (02:00 UTC).
    $start = CarbonImmutable::parse('2026-09-04 00:30:00', 'UTC');

    $due = (new SlaCalculator)->dueAt($start, 60, slaCalendar(), 'Asia/Singapore');

    expect($due->toDateTimeString())->toBe('2026-09-04 02:00:00'); // Fri 10:00 SGT
});

it('dueAt is addBusinessMinutes under a different name', function () {
    $start = CarbonImmutable::parse('2026-09-07 01:00:00', 'UTC'); // Mon 09:00 SGT

    $due = (new SlaCalculator)->dueAt($start, 240, slaCalendar(), 'Asia/Singapore');

    expect($due->toDateTimeString())->toBe('2026-09-07 05:00:00'); // Mon 13:00 SGT
});

it('counts elapsed business minutes inside a single window', function () {
    $calculator = new SlaCalculator;

    $elapsed = $calculator->elapsedBusinessMinutes(
        CarbonImmutable::parse('2026-09-07 01:00:00', 'UTC'), // Mon 09:00 SGT
        CarbonImmutable::parse('2026-09-07 03:00:00', 'UTC'), // Mon 11:00 SGT
        slaCalendar(),
        'Asia/Singapore',
    );

    expect($elapsed)->toBe(120);
});

it('counts elapsed business minutes across a weekend', function () {
    $calculator = new SlaCalculator;

    // Friday 17:00 SGT (09:00 UTC) to Monday 10:00 SGT (02:00 UTC): one hour
    // on Friday and one on Monday.
    $elapsed = $calculator->elapsedBusinessMinutes(
        CarbonImmutable::parse('2026-09-04 09:00:00', 'UTC'),
        CarbonImmutable::parse('2026-09-07 02:00:00', 'UTC'),
        slaCalendar(),
        'Asia/Singapore',
    );

    expect($elapsed)->toBe(120);
});

it('ignores time before the first opening when counting elapsed minutes', function () {
    $calculator = new SlaCalculator;

    // Start Saturday (closed), now Monday 09:30 SGT: only the 30 minutes since
    // the Monday opening count.
    $elapsed = $calculator->elapsedBusinessMinutes(
        CarbonImmutable::parse('2026-09-05 02:00:00', 'UTC'), // Sat 10:00 SGT
        CarbonImmutable::parse('2026-09-07 01:30:00', 'UTC'), // Mon 09:30 SGT
        slaCalendar(),
        'Asia/Singapore',
    );

    expect($elapsed)->toBe(30);
});

it('rejects negative durations', function () {
    (new SlaCalculator)->addBusinessMinutes(
        CarbonImmutable::parse('2026-09-04 01:00:00', 'UTC'),
        -5,
        slaCalendar(),
        'Asia/Singapore',
    );
})->throws(InvalidArgumentException::class);
