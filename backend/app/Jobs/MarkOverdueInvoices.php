<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Notifications\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Flip ISSUED invoices whose due date has passed to OVERDUE (PRD §33). Runs
 * hourly from the scheduler in routes/console.php. The query only ever matches
 * ISSUED rows, so re-runs are idempotent: an OVERDUE invoice is never matched
 * again. Finance users with invoice.view are notified per overdue invoice —
 * there is no customer-portal user mapping yet, so the notification is
 * internal only.
 */
final class MarkOverdueInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Horizon queue tags for this recurring job.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return ['invoice', 'monitor'];
    }

    public function handle(): void
    {
        $overdue = $this->overdue();

        foreach ($overdue as $invoice) {
            $invoice->status = InvoiceStatus::Overdue;
            $invoice->save();

            $this->notify($invoice);
        }
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function overdue(): Collection
    {
        return Invoice::query()
            ->with('customer:id,name')
            ->where('status', InvoiceStatus::Issued->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString())
            ->orderBy('organization_id')
            ->get();
    }

    private function notify(Invoice $invoice): void
    {
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
                null,
                NotificationType::INVOICE_OVERDUE,
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer->name,
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
