<?php

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line of an invoice (PRD §17). The amount column is computed as
 * quantity * unit_price in decimal string math and persisted by the invoice
 * actions so queries and totals never touch float arithmetic (PRD §70).
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 * @property int $sort_order
 * @property Invoice $invoice
 *
 * @method static InvoiceItemFactory factory(...$parameters)
 * @method static Builder|InvoiceItem query()
 */
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'sort_order',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
