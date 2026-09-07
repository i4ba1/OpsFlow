<?php

namespace App\Http\Controllers\Api\V1\Comment;

use App\Actions\Comments\CreateComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Comments\UpdateComment;
use App\Enums\CommentVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Comment;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CommentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:request.view', only: ['index']),
            new Middleware('permission:comment.create', only: ['store']),
            new Middleware('permission:comment.update_own', only: ['update']),
            new Middleware('permission:comment.delete_own', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly CreateComment $createComment,
        private readonly UpdateComment $updateComment,
        private readonly DeleteComment $deleteComment,
    ) {}

    /**
     * GET /api/v1/service-requests/{serviceRequest}/comments — org-scoped
     * comment list. Internal users see PUBLIC and INTERNAL rows; everyone else
     * is filtered down to PUBLIC by the server (PRD §14.2).
     */
    public function index(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertRequestOrganization($serviceRequest, $request->user()->organization_id);

        $perPage = (int) $request->integer('per_page', 20);
        $perPage = min(max($perPage, 1), 100);

        $query = Comment::query()
            ->where('service_request_id', $serviceRequest->id)
            ->with('author:id,name,email');

        if (! $request->user()->hasPermission('comment.internal')) {
            $query->where('visibility', CommentVisibility::Public->value);
        }

        /** @var LengthAwarePaginator<int, Comment> $paginator */
        $paginator = $query->orderBy('created_at')->orderBy('id')->paginate($perPage);

        return response()->json([
            'data' => CommentResource::collection($paginator->items()),
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
     * POST /api/v1/service-requests/{serviceRequest}/comments — add a comment
     * as the authenticated user. INTERNAL visibility requires comment.internal.
     */
    public function store(StoreCommentRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertRequestOrganization($serviceRequest, $request->user()->organization_id);

        $visibility = CommentVisibility::from($request->validated('visibility', CommentVisibility::Public->value));

        if ($visibility === CommentVisibility::Internal) {
            abort_unless($request->user()->hasPermission('comment.internal'), 403);
        }

        $comment = $this->createComment->handle(
            $serviceRequest,
            $request->user(),
            $request->validated('body'),
            $visibility,
        );

        return response()->json([
            'data' => new CommentResource($comment->load('author:id,name,email')),
        ], 201);
    }

    /**
     * PATCH /api/v1/comments/{comment} — edit the body of an own, non-deleted
     * comment (PRD §14.4).
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->assertCommentOrganization($comment, $request->user()->organization_id);

        $updated = $this->updateComment->handle($comment, $request->user(), $request->validated('body'));

        return response()->json([
            'data' => new CommentResource($updated->load('author:id,name,email')),
        ]);
    }

    /**
     * DELETE /api/v1/comments/{comment} — soft-delete an own comment.
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->assertCommentOrganization($comment, $request->user()->organization_id);

        $this->deleteComment->handle($comment, $request->user());

        return response()->json([
            'data' => null,
        ]);
    }

    private function assertCommentOrganization(Comment $comment, string $organizationId): void
    {
        abort_unless($comment->organization_id === $organizationId, 404);
    }

    private function assertRequestOrganization(ServiceRequest $serviceRequest, string $organizationId): void
    {
        abort_unless($serviceRequest->organization_id === $organizationId, 404);
    }
}
