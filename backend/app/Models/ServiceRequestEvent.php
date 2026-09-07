<?php

namespace App\Models;

use App\Enums\ServiceRequestEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $service_request_id
 * @property ServiceRequestEventType $type
 * @property string|null $actor_id
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $note
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $actor
 *
 * @method static Builder|ServiceRequestEvent query()
 */
class ServiceRequestEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'service_request_id',
        'type',
        'actor_id',
        'from_value',
        'to_value',
        'note',
        'metadata',
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

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'type' => ServiceRequestEventType::class,
            'metadata' => 'array',
        ];
    }
}
