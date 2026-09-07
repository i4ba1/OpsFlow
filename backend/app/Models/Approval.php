<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use Database\Factories\ApprovalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An approval decision requested on a service request (PRD §16).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_request_id
 * @property string $requested_by
 * @property string $assigned_to
 * @property ApprovalType $type
 * @property string $title
 * @property string|null $description
 * @property ApprovalStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $responded_at
 * @property string|null $response_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Organization $organization
 * @property ServiceRequest $serviceRequest
 * @property User $requestedBy
 * @property User $assignee
 * @property Collection<int, Attachment> $attachments
 *
 * @method static ApprovalFactory factory(...$parameters)
 * @method static Builder|Approval query()
 */
class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'service_request_id',
        'requested_by',
        'assigned_to',
        'type',
        'title',
        'description',
        'status',
        'due_at',
        'responded_at',
        'response_note',
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'approval_id');
    }

    protected function casts(): array
    {
        return [
            'type' => ApprovalType::class,
            'status' => ApprovalStatus::class,
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * A decision has been made when the approval left the PENDING state.
     */
    public function hasResponded(): bool
    {
        return $this->status !== ApprovalStatus::Pending;
    }
}
