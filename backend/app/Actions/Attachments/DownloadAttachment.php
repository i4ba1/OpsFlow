<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve a stored attachment for download (PRD §15). Local (and test) disks
 * stream the file with its original client filename and stored content type;
 * remote S3-compatible disks fall back to a temporary signed URL. The
 * attachment.download capability and organization scoping are enforced by the
 * route layer before this action runs.
 */
final readonly class DownloadAttachment
{
    public function handle(Attachment $attachment): Response
    {
        $disk = Storage::disk($attachment->storage_disk);

        abort_unless($disk->exists($attachment->storage_path), 404, 'The stored file could not be found.');

        $driver = config("filesystems.disks.{$attachment->storage_disk}.driver", 'local');

        if ($driver === 's3') {
            return response()->redirectGuest($disk->temporaryUrl($attachment->storage_path, now()->addMinutes(5)));
        }

        return $disk->download($attachment->storage_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }
}
