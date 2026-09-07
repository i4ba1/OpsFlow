<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrganizationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:organization.view', only: ['show']),
            new Middleware('permission:organization.update', only: ['update']),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()->organization;

        return response()->json([
            'data' => new OrganizationResource($organization),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $organization = $request->user()->organization;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['SGD', 'MYR', 'IDR', 'USD'])],
        ]);

        $organization->update($validated);

        return response()->json([
            'data' => new OrganizationResource($organization->fresh()),
        ]);
    }
}
