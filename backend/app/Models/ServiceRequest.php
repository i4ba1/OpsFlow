<?php

namespace App\Models;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $request_number
 * @property string $customer_id
 * @property string|null $project_id
 * @property string $requested_by
 * @property string|null $assigned_to
 * @property string $title
 * @property string $description
 * @property string|null $category_id
 * @property ServiceRequestPriority $priority
 * @property ServiceRequestStatus $status
 * @property string|null $sla_policy_id
 * @property Carbon|null $first_response_due_at
 * @property Carbon|null $resolution_due_at
 * @property Carbon|null $first_responded_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Customer $customer
 * @property Project|null $project
 * @property User $requestedBy
 * @property User|null $assignee
 * @property ServiceRequestCategory|null $category
 * @property SlaPolicy|null $policy
 * @property Collection<int, ServiceRequestEvent> $events
 * @property string|null $policy_id SLA monitor join projection (sla_policies.id)
 * @property string|null $timezone SLA monitor join projection (policy timezone)
 * @property string|null $business_calendar_id SLA monitor join projection
 * @property int|null $resolution_minutes SLA monitor join projection
 * @property int|null $warning_threshold_percent SLA monitor join projection
 *
 * @method static ServiceRequestFactory factory(...$parameters)
 * @method static Builder|ServiceRequest query()
 */
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'request_number',
        'customer_id',
        'project_id',
        'requested_by',
        'assigned_to',
        'title',
        'description',
        'category_id',
        'priority',
        'status',
        'sla_policy_id',
        'first_response_due_at',
        'resolution_due_at',
        'first_responded_at',
        'resolved_at',
        'closed_at',
        'version',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ServiceRequestCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceRequestCategory::class);
    }

    /** @return BelongsTo<SlaPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    /** @return HasMany<SlaEvent, $this> */
    public function slaEvents(): HasMany
    {
        return $this->hasMany(SlaEvent::class);
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

    /** @return HasMany<ServiceRequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ServiceRequestEvent::class)->orderBy('created_at')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'priority' => ServiceRequestPriority::class,
            'status' => ServiceRequestStatus::class,
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Requests that are still being worked: any status except the terminal
     * RESOLVED / CLOSED / CANCELLED states (PRD §12).
     *
     * @param  Builder<ServiceRequest>  $query
     * @return Builder<ServiceRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ServiceRequestStatus::Resolved->value,
            ServiceRequestStatus::Closed->value,
            ServiceRequestStatus::Cancelled->value,
        ]);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [
            ServiceRequestStatus::Resolved,
            ServiceRequestStatus::Closed,
            ServiceRequestStatus::Cancelled,
        ], true);
    }

    /**
     * Non-terminal requests can still be edited, assigned and re-prioritized.
     */
    public function isEditable(): bool
    {
        return $this->isOpen();
    }

    /**
     * A request can be assigned while it is not in a terminal state (PRD
     * §8.1).
     */
    public function isAssignable(): bool
    {
        return $this->isOpen();
    }
}
