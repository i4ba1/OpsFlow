<?php

namespace App\Models;

use Database\Factories\CustomerContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $customer_id
 * @property string|null $user_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static CustomerContactFactory factory(...$parameters)
 * @method static Builder|CustomerContact query()
 */
class CustomerContact extends Model
{
    /** @use HasFactory<CustomerContactFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'user_id',
        'name',
        'email',
        'phone',
        'job_title',
        'is_primary',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
