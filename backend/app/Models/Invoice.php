<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer invoice (PRD §17). Created as an editable DRAFT with its totals
 * computed from the line items; issuing allocates the INV number, stamps
 * issued_at and moves the invoice to ISSUED. Money is stored and handled as
 * decimal strings — never float math (PRD §70).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $invoice_number
 * @property string $customer_id
 * @property string|null $project_id
 * @property InvoiceStatus $status
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total_amount
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Organization $organization
 * @property Customer $customer
 * @property Project|null $project
 * @property User|null $createdBy
 * @property Collection<int, InvoiceItem> $items
 *
 * @method static InvoiceFactory factory(...$parameters)
 * @method static Builder|Invoice query()
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'invoice_number',
        'customer_id',
        'project_id',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'total_amount',
        'issued_at',
        'due_at',
        'paid_at',
        'notes',
        'created_by',
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The invoice is still an editable draft (PRD §17).
     */
    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * An invoice that has left the draft state can no longer be edited or
     * issued.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /**
     * Recompute the monetary totals from the current line items using decimal
     * string math (PRD §70): subtotal is the sum of the item amounts and total
     * is subtotal + tax_amount. V1 has no tax engine, so tax_amount is always
     * carried over unchanged.
     */
    public function recalculateTotals(): void
    {
        $subtotal = '0.00';

        foreach ($this->items as $item) {
            $subtotal = bcadd($subtotal, $item->amount, 2);
        }

        $this->subtotal = $subtotal;
        $this->total_amount = bcadd($subtotal, $this->tax_amount, 2);
    }

    /**
     * A single decimal string with two digits of precision.
     */
    public static function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
