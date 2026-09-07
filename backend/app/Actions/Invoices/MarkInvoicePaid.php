<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceAlreadyPaidException;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Mark an ISSUED or OVERDUE invoice as paid (PRD §17). The row is locked and
 * only ISSUED/OVERDUE invoices can be paid; PAID and VOID invoices are
 * terminal and reject the transition.
 *
 * @throws InvoiceAlreadyPaidException
 */
final readonly class MarkInvoicePaid
{
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [InvoiceStatus::Issued, InvoiceStatus::Overdue], true)) {
                throw new InvoiceAlreadyPaidException;
            }

            $locked->status = InvoiceStatus::Paid;
            $locked->paid_at = now();
            $locked->save();

            return $locked->fresh();
        });
    }
}
