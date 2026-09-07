<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications — the current user's inbox, unread first then
     * newest, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = min(max($perPage, 1), 100);

        /** @var User $user */
        $user = $request->user();

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderForInbox()
            ->with('actor:id,name,email');

        /** @var LengthAwarePaginator<int, Notification> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => NotificationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }

    /**
     * GET /api/v1/notifications/unread-count — number of unread notifications
     * for the badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => ['count' => $count],
        ]);
    }

    /**
     * POST /api/v1/notifications/{notification}/read — mark a notification
     * read; only the recipient.
     */
    public function read(Request $request, Notification $notification): JsonResponse
    {
        $this->assertOwnedBy($notification, $request->user()->id);

        $notification->markAsRead();

        return response()->json([
            'data' => new NotificationResource($notification->fresh(['actor:id,name,email'])),
        ]);
    }

    /**
     * POST /api/v1/notifications/read-all — mark every notification of the
     * current user as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        $updated = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['updated' => $updated],
        ]);
    }

    private function assertOwnedBy(Notification $notification, string $userId): void
    {
        abort_unless($notification->user_id === $userId, 404);
    }
}
