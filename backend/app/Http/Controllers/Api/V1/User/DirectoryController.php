<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal active-user directory used to populate assignee/approver pickers.
 *
 * Deliberately separate from GET /users (which requires user.view): managers
 * and operators hold request.assign / approval.request but not user.view, and
 * still need to select an organisation member as the assignee of a request or
 * an approval. Only identity fields are exposed.
 */
final class DirectoryController extends Controller
{
    /**
     * GET /api/v1/directory/users
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->hasPermission('request.assign') || $actor->hasPermission('approval.request'),
            403,
        );

        $users = User::query()
            ->where('organization_id', $actor->organization_id)
            ->where('status', UserStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json([
            'data' => $users->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }
}
