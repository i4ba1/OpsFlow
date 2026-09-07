<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Delete an attachment (PRD §15): soft-delete the row and remove the stored
 * object. The attachment.delete capability is enforced by route middleware.
 */
final readonly class DeleteAttachment
{
    public function handle(Attachment $attachment, User $actor): void
    {
        DB::transaction(function () use ($attachment): void {
            $locked = Attachment::query()
                ->whereKey($attachment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->trashed()) {
                return;
            }

            $locked->delete();

            Storage::disk($locked->storage_disk)->delete($locked->storage_path);
        });
    }
}
