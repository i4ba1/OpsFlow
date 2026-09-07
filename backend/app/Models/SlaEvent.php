<?php

namespace App\Models;

use App\Enums\SlaEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recorded SLA threshold crossing for a service request (PRD §13.5/§13.6):
 * AT_RISK, BREACHED or MET. The unique dedupe_key ("{request id}:{type}")
 * guarantees the monitor never writes the same threshold twice.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_request_id
 * @property string|null $sla_policy_id
 * @property SlaEventType $type
 * @property string|null $priority
 * @property int|null $threshold_percent
 * @property Carbon|null $due_at
 * @property Carbon $occurred_at
 * @property array<string, mixed>|null $metadata
 * @property string $dedupe_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property ServiceRequest $serviceRequest
 * @property SlaPolicy|null $policy
 *
 * @method static Builder|SlaEvent query()
 */
class SlaEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'service_request_id',
        'sla_policy_id',
        'type',
        'priority',
        'threshold_percent',
        'due_at',
        'occurred_at',
        'metadata',
        'dedupe_key',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<SlaPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    protected function casts(): array
    {
        return [
            'type' => SlaEventType::class,
            'threshold_percent' => 'integer',
            'due_at' => 'datetime',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
