<?php

namespace App\Actions\Attachments;

use App\Events\AttachmentUploaded;
use App\Models\Attachment;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Store an uploaded file as a request attachment (PRD §15). The file lands on
 * a server-chosen path attachments/{uuid}/{uuid}.{ext} — never under the client
 * filename — with an sha256 checksum, then the row is committed and
 * AttachmentUploaded fires after commit. When the row insert fails the stored
 * object is removed again so storage and database never drift.
 */
final readonly class UploadAttachment
{
    public function handle(ServiceRequest $serviceRequest, User $uploader, UploadedFile $file): Attachment
    {
        return DB::transaction(function () use ($serviceRequest, $uploader, $file): Attachment {
            $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();
            $id = (string) Str::uuid7();
            $path = "attachments/{$id}/{$id}.".($extension !== '' ? $extension : 'bin');

            $diskName = (string) config('filesystems.default');
            $disk = Storage::disk($diskName);

            $disk->putFileAs(
                dirname($path),
                $file,
                basename($path),
            );

            try {
                $attachment = Attachment::query()->create([
                    'organization_id' => $serviceRequest->organization_id,
                    'service_request_id' => $serviceRequest->id,
                    'uploaded_by' => $uploader->id,
                    'original_name' => $file->getClientOriginalName(),
                    'storage_disk' => $diskName,
                    'storage_path' => $path,
                    'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                    'size_bytes' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath() ?: ''),
                ]);
            } catch (\Throwable $e) {
                $disk->delete($path);

                throw $e;
            }

            DB::afterCommit(fn () => AttachmentUploaded::dispatch($attachment, $uploader));

            return $attachment;
        });
    }
}
