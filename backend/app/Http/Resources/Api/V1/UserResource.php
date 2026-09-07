<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Organization;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $status
 * @property string $timezone
 * @property Organization $organization
 * @property Collection<int, Role> $roles
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'timezone' => $this->timezone,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->whenLoaded('roles', fn () => $this->permissionNames()),
        ];
    }

    /**
     * Aggregate the permission names across the loaded roles of the user.
     *
     * @return list<string>
     */
    private function permissionNames(): array
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }
}
