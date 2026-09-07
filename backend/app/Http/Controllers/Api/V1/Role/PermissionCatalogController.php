<?php

namespace App\Http\Controllers\Api\V1\Role;

use App\Http\Controllers\Controller;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PermissionCatalogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:role.view'),
        ];
    }

    /**
     * GET /api/v1/roles/permissions-catalog — the canonical permission
     * catalog grouped by module (PRD §8).
     */
    public function __invoke(): JsonResponse
    {
        $catalog = PermissionSeeder::catalog();

        $groups = [];

        foreach ($catalog as $permission) {
            $groups[$permission['group']][] = $permission['name'];
        }

        $data = [];

        foreach ($groups as $group => $permissions) {
            $data[] = [
                'group' => $group,
                'permissions' => $permissions,
            ];
        }

        return response()->json([
            'data' => $data,
        ]);
    }
}
