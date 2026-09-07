<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-priority SLA targets of a policy (PRD §13.2): business minutes for a
 * first response and for resolution, and the warning threshold percent at
 * which a request turns AT_RISK.
 *
 * @property string $id
 * @property string $sla_policy_id
 * @property string $priority
 * @property int $first_response_minutes
 * @property int $resolution_minutes
 * @property int $warning_threshold_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property SlaPolicy $policy
 *
 * @method static Builder|SlaTarget query()
 */
class SlaTarget extends Model
{
    use HasUuids;

    protected $fillable = [
        'sla_policy_id',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
        'warning_threshold_percent',
    ];

    /** @return BelongsTo<SlaPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    protected function casts(): array
    {
        return [
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'warning_threshold_percent' => 'integer',
        ];
    }
}
