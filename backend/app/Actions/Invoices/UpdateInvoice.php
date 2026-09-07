<?php

namespace App\Actions\Invoices;

use App\Exceptions\InvoiceNotEditableException;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Update the mutable fields of a DRAFT invoice (customer, project, currency,
 * due date, notes) and replace its line items with the submitted set, then
 * recompute the totals — all inside a single transaction (PRD §17). The row is
 * locked so two concurrent edits cannot interleave; once the invoice has left
 * the DRAFT state (issued/paid/void) it is immutable.
 */
final readonly class UpdateInvoice
{
    /**
     * @param  array{customer_id?: string, project_id?: string|null, currency?: string, due_at?: string|null, notes?: string|null, items?: array<int, array{description: string, quantity: string|int|float, unit_price: string|int|float}>}  $data
     *
     * @throws InvoiceNotEditableException
     */
    public function handle(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isEditable()) {
                throw new InvoiceNotEditableException;
            }

            $attributes = [];

            foreach (['customer_id', 'project_id', 'currency', 'due_at', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if ($attributes !== []) {
                $locked->fill($attributes);
            }

            if (array_key_exists('items', $data)) {
                $locked->items()->delete();
                $locked->items()->createMany($this->normalizedItems($data['items']));
                $locked->load('items');
            }

            $locked->recalculateTotals();
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
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
