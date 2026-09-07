<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CommentVisibility;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $service_request_id
 * @property string $author_id
 * @property string $body
 * @property CommentVisibility $visibility
 * @property User $author
 * @property ServiceRequest $serviceRequest
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_request_id' => $this->service_request_id,
            'body' => $this->body,
            'visibility' => $this->visibility,
            'author' => [
                'id' => $this->author_id,
                'name' => $this->author->name,
            ],
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
