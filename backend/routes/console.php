<?php

use App\Jobs\CheckActiveServiceRequestSlas;
use App\Jobs\ExpirePendingApprovals;
use App\Jobs\MarkOverdueInvoices;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SLA monitor (PRD §13.5): scan open requests with an active policy every
// minute and record AT_RISK/BREACHED events and notifications. The unique
// sla_events.dedupe_key makes re-runs harmless; withoutOverlapping guards the
// per-minute cadence against long-running sweeps. The class name (rather than
// an instance) is registered so the container builds the job — and its SLA
// services — when the scheduler runs it.
Schedule::job(CheckActiveServiceRequestSlas::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Check active service request SLAs');

// Approval expiry (PRD §33): approvals past their due date move PENDING ->
// EXPIRED. The query only matches PENDING rows, so re-runs never double-expire.
Schedule::job(ExpirePendingApprovals::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Expire pending approvals past their due date');

// Invoice overdue sweep (PRD §33): ISSUED invoices whose due date has passed
// move to OVERDUE. The query only matches ISSUED rows with a due date before
// today, so re-runs never double-flip an invoice.
Schedule::job(MarkOverdueInvoices::class)
    ->hourly()
    ->withoutOverlapping()
    ->description('Mark issued invoices past their due date as overdue');
