<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 * @property Collection<int, Permission> $permissions
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
        ];
    }
}
