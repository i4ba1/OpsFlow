<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Exceptions\ApprovalAlreadyRespondedException;
use App\Models\Approval;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a still-pending approval (PRD §16). Only the user who requested the
 * approval or a manager holding approval.cancel may cancel; that ownership
 * check lives in the controller. Cancelling an approval that has already been
 * responded to is refused — decisions are immutable.
 */
final readonly class CancelApproval
{
    /**
     * @throws ApprovalAlreadyRespondedException
     */
    public function handle(Approval $approval): Approval
    {
        return DB::transaction(function () use ($approval): Approval {
            $locked = Approval::query()
                ->whereKey($approval->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ApprovalStatus::Pending) {
                throw new ApprovalAlreadyRespondedException('This approval has already been responded to.');
            }

            $locked->status = ApprovalStatus::Cancelled;
            $locked->save();

            return $locked;
        });
    }
}
