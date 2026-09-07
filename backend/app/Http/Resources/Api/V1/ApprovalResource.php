<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $service_request_id
 * @property string $requested_by
 * @property string $assigned_to
 * @property ApprovalType $type
 * @property string $title
 * @property string|null $description
 * @property ApprovalStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $responded_at
 * @property string|null $response_note
 * @property ServiceRequest $serviceRequest
 * @property User $requestedBy
 * @property User $assignee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_request_id' => $this->service_request_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_at' => $this->due_at,
            'responded_at' => $this->responded_at,
            'response_note' => $this->response_note,
            'requested_by' => new UserResource($this->whenLoaded('requestedBy')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'service_request' => new ServiceRequestResource($this->whenLoaded('serviceRequest')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
