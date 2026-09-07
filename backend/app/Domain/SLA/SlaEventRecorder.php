<?php

namespace App\Domain\SLA;

use App\Enums\SlaEventType;
use App\Models\SlaEvent;
use Illuminate\Support\Carbon;

/**
 * Persists one SLA event row (PRD §13.5/§13.6). Callers create events only
 * after acquiring the per-threshold lock (firstOrCreate on dedupe_key); the
 * unique constraint on dedupe_key is the backstop that prevents double
 * AT_RISK/BREACHED notifications even under races.
 */
final class SlaEventRecorder
{
    /**
     * The dedupe key identifying one threshold crossing of one request.
     */
    public function dedupeKey(string $serviceRequestId, SlaEventType $type): string
    {
        return $serviceRequestId.':'.$type->value;
    }

    /**
     * Record the event for a request unless it was already recorded.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordOnce(
        string $organizationId,
        string $serviceRequestId,
        SlaEventType $type,
        ?string $slaPolicyId = null,
        ?string $priority = null,
        ?int $thresholdPercent = null,
        ?Carbon $dueAt = null,
        ?array $metadata = null,
    ): SlaEvent {
        $occurredAt = Carbon::now();

        /** @var SlaEvent $event */
        $event = SlaEvent::query()->firstOrCreate(
            ['dedupe_key' => $this->dedupeKey($serviceRequestId, $type)],
            [
                'organization_id' => $organizationId,
                'service_request_id' => $serviceRequestId,
                'sla_policy_id' => $slaPolicyId,
                'type' => $type->value,
                'priority' => $priority,
                'threshold_percent' => $thresholdPercent,
                'due_at' => $dueAt,
                'occurred_at' => $occurredAt,
                'metadata' => $metadata,
            ],
        );

        return $event;
    }
}
