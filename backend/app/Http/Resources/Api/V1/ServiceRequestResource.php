<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ServiceRequestCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $request_number
 * @property string $customer_id
 * @property string|null $project_id
 * @property string|null $category_id
 * @property string $title
 * @property string $description
 * @property ServiceRequestStatus $status
 * @property ServiceRequestPriority $priority
 * @property Customer $customer
 * @property Project|null $project
 * @property User $requestedBy
 * @property User|null $assignee
 * @property ServiceRequestCategory|null $category
 * @property Carbon|null $first_response_due_at
 * @property Carbon|null $resolution_due_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property int $version
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'category_id' => $this->category_id,
            'category' => new ServiceRequestCategoryResource($this->whenLoaded('category')),
            'requested_by' => new UserResource($this->whenLoaded('requestedBy')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'first_response_due_at' => $this->first_response_due_at,
            'resolution_due_at' => $this->resolution_due_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
