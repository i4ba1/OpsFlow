<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create a DRAFT invoice with auto-numbered items and computed totals inside a
 * single transaction (PRD §17). Line item amounts (quantity * unit_price) and
 * the invoice totals are computed with decimal string math — never floats
 * (PRD §70). The invoice number is allocated at issue time, so the draft only
 * carries a placeholder (INV-{year}-{random}) until then.
 */
final readonly class CreateInvoice
{
    /**
     * @param  array{customer_id: string, project_id?: string|null, currency?: string, due_at?: string|null, notes?: string|null, items: array<int, array{description: string, quantity: string|int|float, unit_price: string|int|float}>}  $data
     */
    public function handle(User $actor, array $data): Invoice
    {
        return DB::transaction(function () use ($actor, $data): Invoice {
            $invoice = Invoice::query()->create([
                'organization_id' => $actor->organization_id,
                'invoice_number' => 'INV-'.now()->format('Y').'-'.str_pad(
                    (string) random_int(100000, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT,
                ),
                'customer_id' => $data['customer_id'],
                'project_id' => $data['project_id'] ?? null,
                'currency' => $data['currency'] ?? 'SGD',
                'due_at' => $data['due_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => InvoiceStatus::Draft->value,
                'created_by' => $actor->id,
            ]);

            $invoice->items()->createMany($this->normalizedItems($data['items']));

            $invoice->load('items');
            $invoice->recalculateTotals();
            $invoice->save();

            return $invoice->fresh();
        });
    }

    /**
     * Map the submitted raw items into stored rows with decimal string money.
     *
     * @param  array<int, array{description: string, quantity: string|int|float, unit_price: string|int|float}>  $items
     * @return list<array{description: string, quantity: string, unit_price: string, amount: string, sort_order: int}>
     */
    private function normalizedItems(array $items): array
    {
        $rows = [];

        foreach (array_values($items) as $index => $item) {
            $quantity = Invoice::money((string) $item['quantity']);
            $unitPrice = Invoice::money((string) $item['unit_price']);

            $rows[] = [
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => bcmul($quantity, $unitPrice, 2),
                'sort_order' => $index,
            ];
        }

        return $rows;
    }
}
