<?php

namespace App\Events;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after an attachment has been stored and its row committed (PRD
 * §15). The file object is persisted before the row is written, so a database
 * failure cleans the object up and no event is fired.
 */
final class AttachmentUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Attachment $attachment,
        public readonly User $actor,
    ) {}
}
