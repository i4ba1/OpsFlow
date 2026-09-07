<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceNotEditableException;
use App\Models\Invoice;
use App\Models\User;
use App\Support\InvoiceNumberGenerator;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\DB;

/**
 * Issue a DRAFT invoice (PRD §17): allocate the concurrency-safe INV number for
 * the organization and year, stamp issued_at and move the invoice to ISSUED.
 * When the draft has no due date it defaults to 30 days after issue. The
 * affected finance users are notified inside the same transaction, mirroring
 * the approval actions; no audit row is written until the audit module lands
 * in Phase 9.
 *
 * @throws InvoiceNotEditableException
 */
final readonly class IssueInvoice
{
    public function __construct(
        private InvoiceNumberGenerator $numbers,
    ) {}

    public function handle(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isEditable()) {
                throw new InvoiceNotEditableException;
            }

            $locked->invoice_number = $this->numbers->next($locked->organization_id);
            $locked->status = InvoiceStatus::Issued;
            $locked->issued_at = now();
            $locked->due_at ??= now()->addDays(30)->toDateString();
            $locked->save();

            $this->notifyFinance($locked, $actor);

            return $locked->fresh();
        });
    }

    private function notifyFinance(Invoice $invoice, User $actor): void
    {
        $customer = $invoice->customer;

        /** @var list<User> $recipients */
        $recipients = User::query()
            ->where('organization_id', $invoice->organization_id)
            ->whereHas('roles.permissions', fn ($query) => $query->whereKey('invoice.view'))
            ->limit(10)
            ->get()
            ->all();

        foreach ($recipients as $recipient) {
            NotificationDispatcher::dispatch(
                $recipient,
                $actor,
                NotificationType::INVOICE_ISSUED,
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $customer->name,
                    'total_amount' => $invoice->total_amount,
                    'currency' => $invoice->currency,
                    'due_at' => $invoice->due_at?->toDateString(),
                ],
                'invoice',
                $invoice->id,
            );
        }
    }
}
