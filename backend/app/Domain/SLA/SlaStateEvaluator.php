<?php

namespace App\Domain\SLA;

use App\Enums\SlaState;
use App\Models\ServiceRequest;
use App\Models\SlaTarget;
use Carbon\CarbonImmutable;

/**
 * Derives the SLA state of a request at a point in time (PRD §13.4):
 *
 *  - resolved/closed requests with a resolution due date: MET when resolved
 *    within time, BREACHED when the due date was missed;
 *  - open requests without a policy or without a due date: null (no SLA);
 *  - open requests: BREACHED once the whole resolution budget is consumed,
 *    AT_RISK once the target's warning percent is consumed, otherwise
 *    HEALTHY. Progress is measured in business minutes.
 */
final class SlaStateEvaluator
{
    public function __construct(
        private readonly SlaCalculator $calculator,
    ) {}

    /**
     * Full evaluation for a request whose policy relation is loaded (with
     * targets and business calendar).
     */
    public function evaluate(ServiceRequest $serviceRequest, CarbonImmutable $now): ?SlaState
    {
        $terminal = $this->terminalState($serviceRequest);

        if ($terminal !== null) {
            return $terminal;
        }

        if ($serviceRequest->policy === null || $serviceRequest->resolution_due_at === null) {
            return null;
        }

        $target = $serviceRequest->policy->targets
            ->first(fn (SlaTarget $candidate): bool => $candidate->priority === $serviceRequest->priority->value);

        if ($target === null) {
            return null;
        }

        $calendar = $serviceRequest->policy->business_calendar_id !== null
            && $serviceRequest->policy->businessCalendar !== null
                ? BusinessCalendar::fromModel($serviceRequest->policy->businessCalendar)
                : null;

        return $this->progressState(
            $serviceRequest,
            $now,
            (int) $target->resolution_minutes,
            (int) $target->warning_threshold_percent,
            $calendar,
            $serviceRequest->policy->timezone,
        );
    }

    /**
     * Evaluation for the monitoring job, which avoids loading full policy
     * relations: the caller passes the already-known terminal state (or null)
     * and the raw target numbers read from the query.
     */
    public function derive(
        ServiceRequest $serviceRequest,
        CarbonImmutable $now,
        ?SlaState $terminal,
        int $resolutionMinutes,
        int $warningThresholdPercent,
        ?BusinessCalendar $calendar,
        string $timezone,
    ): ?SlaState {
        if ($terminal !== null) {
            return $terminal;
        }

        if ($serviceRequest->resolution_due_at === null) {
            return null;
        }

        return $this->progressState(
            $serviceRequest,
            $now,
            $resolutionMinutes,
            $warningThresholdPercent,
            $calendar,
            $timezone,
        );
    }

    /**
     * MET when the request was resolved within its resolution due date,
     * BREACHED when it was resolved late or is still open past the due date.
     * Requests without a resolution due date never yield a terminal state.
     */
    public function terminalState(ServiceRequest $serviceRequest): ?SlaState
    {
        if ($serviceRequest->resolution_due_at === null) {
            return null;
        }

        $resolvedAt = $serviceRequest->resolved_at;

        if ($resolvedAt === null) {
            return null;
        }

        return CarbonImmutable::make($resolvedAt)->lessThanOrEqualTo(
            CarbonImmutable::make($serviceRequest->resolution_due_at),
        ) ? SlaState::Met : SlaState::Breached;
    }

    private function progressState(
        ServiceRequest $serviceRequest,
        CarbonImmutable $now,
        int $resolutionMinutes,
        int $warningThresholdPercent,
        ?BusinessCalendar $calendar,
        string $timezone,
    ): SlaState {
        if ($calendar !== null && $serviceRequest->created_at !== null) {
            $elapsed = $this->calculator->elapsedBusinessMinutes(
                CarbonImmutable::make($serviceRequest->created_at),
                $now,
                $calendar,
                $timezone,
            );
        } else {
            // A policy without a business calendar falls back to wall-clock
            // progress against the resolution budget.
            $elapsed = (int) $now->diffInMinutes(
                CarbonImmutable::make($serviceRequest->created_at ?? $now),
            );
        }

        $percent = (int) round(($elapsed / max(1, $resolutionMinutes)) * 100);

        if ($percent >= 100) {
            return SlaState::Breached;
        }

        if ($percent >= $warningThresholdPercent) {
            return SlaState::AtRisk;
        }

        return SlaState::Healthy;
    }
}
