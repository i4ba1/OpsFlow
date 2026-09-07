<?php

use App\Jobs\CheckActiveServiceRequestSlas;
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
