<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $service_request_id
 * @property string|null $comment_id
 * @property string $original_name
 * @property string $storage_disk
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $checksum
 * @property Carbon|null $created_at
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_request_id' => $this->service_request_id,
            'comment_id' => $this->comment_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'created_at' => $this->created_at,
        ];
    }
}
