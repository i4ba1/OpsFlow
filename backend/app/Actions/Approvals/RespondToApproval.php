<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\ServiceRequestEventType;
use App\Exceptions\ApprovalAlreadyRespondedException;
use App\Models\Approval;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\DB;

/**
 * Record an approval decision (PRD §16). The approval row is locked, only a
 * PENDING approval can be decided, and the outcome — APPROVED or REJECTED —
 * is persisted together with responded_at and the optional response_note.
 * Decisions are immutable: once responded the approval can never be decided
 * again (only an explicit reset/reissue creates a fresh PENDING approval).
 * The timeline row and the notification to the requester land in the same
 * transaction.
 */
final readonly class RespondToApproval
{
    /**
     * @throws ApprovalAlreadyRespondedException
     */
    public function handle(
        Approval $approval,
        ApprovalStatus $decision,
        User $actor,
        ?string $responseNote = null,
    ): Approval {
        if (! in_array($decision, [ApprovalStatus::Approved, ApprovalStatus::Rejected], true)) {
            throw new \InvalidArgumentException('An approval decision must be APPROVED or REJECTED.');
        }

        return DB::transaction(function () use ($approval, $decision, $actor, $responseNote): Approval {
            $locked = Approval::query()
                ->whereKey($approval->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPending($locked);

            $locked->status = $decision;
            $locked->responded_at = now();
            $locked->response_note = $responseNote;
            $locked->save();

            $this->recordTimelineEvent($locked, $actor);
            $this->notifyRequester($locked, $actor, $decision);

            return $locked;
        });
    }

    /**
     * @throws ApprovalAlreadyRespondedException
     */
    private function assertPending(Approval $approval): void
    {
        if (! $approval->hasResponded()) {
            return;
        }

        throw new ApprovalAlreadyRespondedException;
    }

    private function recordTimelineEvent(Approval $approval, User $actor): void
    {
        $serviceRequest = $approval->serviceRequest;

        $serviceRequest->events()->create([
            'organization_id' => $approval->organization_id,
            'type' => ServiceRequestEventType::ApprovalResponded,
            'actor_id' => $actor->id,
            'from_value' => $approval->status === ApprovalStatus::Approved ? null : $approval->status->value,
            'to_value' => $approval->status->value,
            'note' => $approval->response_note,
            'metadata' => [
                'approval_id' => $approval->id,
                'title' => $approval->title,
                'response' => $approval->status->value,
            ],
        ]);
    }

    private function notifyRequester(Approval $approval, User $actor, ApprovalStatus $decision): void
    {
        $serviceRequest = $approval->serviceRequest;

        NotificationDispatcher::dispatch(
            $approval->requestedBy,
            $actor,
            NotificationType::APPROVAL_RESPONDED,
            [
                'service_request_id' => $serviceRequest->id,
                'request_number' => $serviceRequest->request_number,
                'approval_id' => $approval->id,
                'title' => $approval->title,
                'response' => $decision->value,
            ],
            'approval',
            $approval->id,
        );
    }
}
