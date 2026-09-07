<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $customer_code
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $registration_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $billing_email
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $country_code
 * @property CustomerStatus $status
 * @property array<string, mixed>|null $metadata
 * @property string|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static CustomerFactory factory(...$parameters)
 * @method static Builder|Customer query()
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'customer_code',
        'name',
        'legal_name',
        'registration_number',
        'email',
        'phone',
        'billing_email',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'country_code',
        'status',
        'metadata',
        'created_by',
        'archived_at',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<CustomerContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'metadata' => 'array',
            'archived_at' => 'datetime',
        ];
    }
}
