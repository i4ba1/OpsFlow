<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceAlreadyPaidException;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Void an invoice (PRD §17). DRAFT, ISSUED and OVERDUE invoices can be voided;
 * a PAID invoice is terminal and rejects the transition. The invoice_number
 * and issued_at are kept so the voided document stays traceable.
 *
 * @throws InvoiceAlreadyPaidException
 */
final readonly class VoidInvoice
{
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === InvoiceStatus::Paid) {
                throw new InvoiceAlreadyPaidException;
            }

            $locked->status = InvoiceStatus::Void;
            $locked->save();

            return $locked->fresh();
        });
    }
}
