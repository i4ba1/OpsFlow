<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OrganizationStatus;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    /**
     * POST /api/v1/auth/login (FR-AUTH-001).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Do not reveal whether an email exists.
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password ?? '')) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active.'],
            ]);
        }

        if ($user->organization?->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'email' => ['Your organization is suspended.'],
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        $user->load(['organization', 'roles.permissions']);

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ]);
    }
}
