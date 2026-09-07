<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\ServiceRequestEventType;
use App\Models\Approval;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Create an approval request against a service request (PRD §16). The
 * requesting user is recorded as requested_by, the approval starts in PENDING,
 * an APPROVAL_REQUESTED row lands on the unified request timeline and the
 * assignee is notified after the transaction commits. The controller is
 * responsible for verifying the request belongs to the caller's organization
 * and for holding the approval.request permission.
 */
final readonly class RequestApproval
{
    /**
     * @param  array{title: string, description?: string|null, type: ApprovalType, due_at?: Carbon|null}  $data
     */
    public function handle(ServiceRequest $serviceRequest, User $requester, User $assignee, array $data): Approval
    {
        return DB::transaction(function () use ($serviceRequest, $requester, $assignee, $data): Approval {
            $approval = Approval::query()->create([
                'organization_id' => $serviceRequest->organization_id,
                'service_request_id' => $serviceRequest->id,
                'requested_by' => $requester->id,
                'assigned_to' => $assignee->id,
                'type' => $data['type']->value,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => ApprovalStatus::Pending->value,
                'due_at' => $data['due_at'] ?? null,
            ]);

            $serviceRequest->events()->create([
                'organization_id' => $serviceRequest->organization_id,
                'type' => ServiceRequestEventType::ApprovalRequested,
                'actor_id' => $requester->id,
                'to_value' => $approval->id,
                'note' => $approval->title,
                'metadata' => [
                    'approval_id' => $approval->id,
                    'type' => $approval->type->value,
                ],
            ]);

            NotificationDispatcher::dispatch(
                $assignee,
                $requester,
                NotificationType::APPROVAL_REQUESTED,
                [
                    'service_request_id' => $serviceRequest->id,
                    'request_number' => $serviceRequest->request_number,
                    'approval_id' => $approval->id,
                    'title' => $approval->title,
                ],
                'approval',
                $approval->id,
            );

            return $approval;
        });
    }
}
