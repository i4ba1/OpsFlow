<?php

namespace App\Jobs;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Expire pending approvals whose due date has passed (PRD §16, PRD §33). Runs
 * every five minutes from the scheduler in routes/console.php. The query only
 * ever matches PENDING rows, so re-runs and overlapping runs are harmless:
 * an expired approval is never matched again and no timeline/notification is
 * emitted for a machine-driven expiry (the status change itself is the
 * record).
 */
final class ExpirePendingApprovals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Horizon queue tags for this recurring job.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return ['approval', 'monitor'];
    }

    public function handle(): void
    {
        Approval::query()
            ->where('status', ApprovalStatus::Pending->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->update(['status' => ApprovalStatus::Expired->value]);
    }
}
