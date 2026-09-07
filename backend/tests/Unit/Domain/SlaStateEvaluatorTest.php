<?php

use App\Domain\SLA\SlaStateEvaluator;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\SlaState;
use App\Models\BusinessCalendar as BusinessCalendarModel;
use App\Models\BusinessCalendarHoliday;
use App\Models\BusinessCalendarHour;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\SlaPolicy;
use App\Models\SlaTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A persisted Mon-Fri 09:00-18:00 Asia/Singapore calendar. The evaluator reads
 * real model rows, so the calendar model is created with hours and holidays
 * attached. Helper instants are expressed in UTC; Singapore is UTC+8.
 */
function slaBoundaryCalendar(array $holidayDates = []): BusinessCalendarModel
{
    $calendar = BusinessCalendarModel::query()->create([
        'organization_id' => Organization::factory()->create()->id,
        'name' => 'Boundary Calendar',
        'timezone' => 'Asia/Singapore',
    ]);

    foreach ([1, 2, 3, 4, 5] as $day) {
        BusinessCalendarHour::query()->create([
            'business_calendar_id' => $calendar->id,
            'day_of_week' => $day,
            'opens_at' => '09:00:00',
            'closes_at' => '18:00:00',
        ]);
    }

    foreach ($holidayDates as $date) {
        BusinessCalendarHoliday::query()->create([
            'business_calendar_id' => $calendar->id,
            'name' => 'Test holiday',
            'date' => $date,
        ]);
    }

    return $calendar;
}

/**
 * A request created at an exact UTC instant with a policy/target/calendar
 * attached and due dates loaded from the persistence attributes.
 */
function slaBoundaryRequest(
    BusinessCalendarModel $calendar,
    string $createdAtUtc,
    ?string $resolutionDueAtUtc = null,
    array $attributes = [],
): ServiceRequest {
    $org = $calendar->organization;
    $policy = SlaPolicy::query()->create([
        'organization_id' => $org->id,
        'name' => 'Boundary Policy',
        'timezone' => 'Asia/Singapore',
        'business_calendar_id' => $calendar->id,
        'active' => true,
    ]);

    SlaTarget::query()->create([
        'sla_policy_id' => $policy->id,
        'priority' => ServiceRequestPriority::High->value,
        'first_response_minutes' => 60,
        'resolution_minutes' => 480, // one working day, 09:00-18:00
        'warning_threshold_percent' => 75,
    ]);

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    // Requests created Mon 09:00 SGT with a one-day resolution budget are due
    // Tue 09:00 SGT (2026-09-08 01:00 UTC) unless the test overrides it.
    $dueAt = $resolutionDueAtUtc ?? '2026-09-08 01:00:00';

    /** @var ServiceRequest $request */
    $request = ServiceRequest::query()->create(array_merge([
        'organization_id' => $org->id,
        'request_number' => 'REQ-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'title' => 'Boundary request',
        'description' => 'Evaluator boundary.',
        'priority' => ServiceRequestPriority::High->value,
        'status' => ServiceRequestStatus::New->value,
        'sla_policy_id' => $policy->id,
        'resolution_due_at' => $dueAt,
        'version' => 1,
    ], $attributes));

    // created_at is guarded, so it is pinned explicitly to control the clock.
    $request->forceFill(['created_at' => $createdAtUtc])->save();
    $request->refresh();

    $request->setRelation('policy', $policy->load(['targets', 'businessCalendar']));

    return $request;
}

it('reports null when the request has no policy or no due date', function () {
    $calendar = slaBoundaryCalendar();
    $request = slaBoundaryRequest($calendar, '2026-09-07 01:00:00');

    $request->forceFill(['sla_policy_id' => null])->save();
    $request->setRelation('policy', null);
    $request->resolution_due_at = null;

    $state = app(SlaStateEvaluator::class)->evaluate($request, CarbonImmutable::parse('2026-09-08 01:00:00', 'UTC'));

    expect($state)->toBeNull();
});

it('reports HEALTHY at 50% of the resolution budget', function () {
    $calendar = slaBoundaryCalendar();
    $request = slaBoundaryRequest($calendar, '2026-09-07 01:00:00'); // Mon 09:00 SGT

    $state = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-07 05:00:00', 'UTC'), // Mon 13:00 SGT = 4 of 8 hours
    );

    expect($state)->toBe(SlaState::Healthy);
});

it('reports AT_RISK once the warning percent (75%) is consumed', function () {
    $calendar = slaBoundaryCalendar();
    $request = slaBoundaryRequest($calendar, '2026-09-07 01:00:00'); // Mon 09:00 SGT

    // Mon 15:00 SGT = 6 of 8 working hours = 75% of the budget.
    $state = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-07 07:00:00', 'UTC'),
    );

    expect($state)->toBe(SlaState::AtRisk);
});

it('reports BREACHED at or past 100% of the resolution budget', function () {
    $calendar = slaBoundaryCalendar();
    $request = slaBoundaryRequest($calendar, '2026-09-07 01:00:00'); // Mon 09:00 SGT

    // 8 working hours consumed exactly: end of the Monday window.
    $atBudget = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-07 10:00:00', 'UTC'), // Mon 18:00 SGT
    );
    $overBudget = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-08 01:00:00', 'UTC'), // Tue 09:00 SGT
    );

    expect($atBudget)->toBe(SlaState::Breached);
    expect($overBudget)->toBe(SlaState::Breached);
});

it('reports MET when resolved inside the resolution window', function () {
    $calendar = slaBoundaryCalendar();
    $request = slaBoundaryRequest(
        $calendar,
        '2026-09-07 01:00:00',
        resolutionDueAtUtc: '2026-09-07 09:00:00', // Mon 17:00 SGT
        attributes: [
            'status' => ServiceRequestStatus::Resolved->value,
            'resolved_at' => '2026-09-07 05:30:00', // Mon 13:30 SGT, before due
        ],
    );

    $state = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-08 01:00:00', 'UTC'),
    );

    expect($state)->toBe(SlaState::Met);
});

it('reports BREACHED when resolved past the resolution due date', function () {
    $calendar = slaBoundaryCalendar();
    $request = slaBoundaryRequest(
        $calendar,
        '2026-09-07 01:00:00',
        resolutionDueAtUtc: '2026-09-07 09:00:00', // Mon 17:00 SGT
        attributes: [
            'status' => ServiceRequestStatus::Resolved->value,
            'resolved_at' => '2026-09-08 01:00:00', // Tue 09:00 SGT, after due
        ],
    );

    $state = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-08 02:00:00', 'UTC'),
    );

    expect($state)->toBe(SlaState::Breached);
});

it('excludes non-working hours from the percentage consumed', function () {
    $calendar = slaBoundaryCalendar();
    $request = slaBoundaryRequest($calendar, '2026-09-04 01:00:00', resolutionDueAtUtc: '2026-09-07 01:00:00');

    // Friday 09:00 SGT (01:00 UTC) through Monday 09:00 SGT (01:00 UTC)
    // spans a weekend, yet only 8 business hours (100%) have elapsed. A
    // wall-clock reading would overstate the request as breached at Friday
    // noon SGT already.
    $fridayNoon = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-04 04:00:00', 'UTC'), // Fri 12:00 SGT = 3 business hours = 37.5%
    );

    expect($fridayNoon)->toBe(SlaState::Healthy);

    $mondayMorning = app(SlaStateEvaluator::class)->evaluate(
        $request,
        CarbonImmutable::parse('2026-09-07 01:00:00', 'UTC'), // Mon 09:00 SGT = 8 business hours = 100%
    );

    expect($mondayMorning)->toBe(SlaState::Breached);
});
