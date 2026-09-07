<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $name
 * @property string|null $description
 * @property string $group
 */
class Permission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'group',
    ];

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_name', 'role_id');
    }
}
