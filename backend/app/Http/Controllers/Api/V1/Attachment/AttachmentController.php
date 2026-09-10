<?php

namespace App\Http\Controllers\Api\V1\Attachment;

use App\Actions\Attachments\DeleteAttachment;
use App\Actions\Attachments\DownloadAttachment;
use App\Actions\Attachments\UploadAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Models\Attachment;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:request.view', only: ['index', 'store']),
            new Middleware('permission:attachment.create', only: ['store']),
            new Middleware('permission:attachment.download', only: ['download']),
            new Middleware('permission:attachment.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly UploadAttachment $uploadAttachment,
        private readonly DownloadAttachment $downloadAttachment,
        private readonly DeleteAttachment $deleteAttachment,
    ) {}

    /**
     * GET /api/v1/service-requests/{serviceRequest}/attachments — list the
     * non-deleted attachments uploaded against a request, newest first.
     */
    public function index(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertRequestOrganization($serviceRequest, $request->user()->organization_id);

        return response()->json([
            'data' => AttachmentResource::collection($serviceRequest->attachments()->get()),
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }

    /**
     * POST /api/v1/service-requests/{serviceRequest}/attachments — store a
     * multipart file against a request and return the attachment row (201).
     */
    public function store(StoreAttachmentRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertRequestOrganization($serviceRequest, $request->user()->organization_id);

        $attachment = $this->uploadAttachment->handle(
            $serviceRequest,
            $request->user(),
            $request->file('file'),
        );

        return response()->json([
            'data' => new AttachmentResource($attachment),
        ], 201);
    }

    /**
     * GET /api/v1/attachments/{attachment}/download — stream the stored file
     * with its original name.
     */
    public function download(Request $request, Attachment $attachment): Response
    {
        $this->assertAttachmentOrganization($attachment, $request->user()->organization_id);

        abort_if($attachment->trashed(), 404, 'This attachment has been deleted.');

        return $this->downloadAttachment->handle($attachment);
    }

    /**
     * DELETE /api/v1/attachments/{attachment} — soft-delete the row and remove
     * the stored object.
     */
    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->assertAttachmentOrganization($attachment, $request->user()->organization_id);

        $this->deleteAttachment->handle($attachment, $request->user());

        return response()->json([
            'data' => null,
        ]);
    }

    private function assertAttachmentOrganization(Attachment $attachment, string $organizationId): void
    {
        abort_unless($attachment->organization_id === $organizationId, 404);
    }

    private function assertRequestOrganization(ServiceRequest $serviceRequest, string $organizationId): void
    {
        abort_unless($serviceRequest->organization_id === $organizationId, 404);
    }
}
