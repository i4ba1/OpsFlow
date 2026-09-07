<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed upload MIME types (PRD §15.2)
    |--------------------------------------------------------------------------
    |
    | Only these MIME types may be stored as attachments. The allowlist is
    | enforced by the StoreAttachmentRequest through Laravel's mimes rule,
    | which maps each MIME type to its file extension.
    |
    */

    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'text/markdown',
        'application/json',
        'application/xml',
        'text/xml',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/zip',
        'application/gzip',
        'application/x-7z-compressed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed extensions
    |--------------------------------------------------------------------------
    |
    | Mirrors allowed_mime_types so Laravel's mimes rule (which only inspects
    | the client extension) can be combined with an explicit server-side MIME
    | sniff check performed by the upload action.
    |
    */

    'extensions' => [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'csv',
        'md',
        'json',
        'xml',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'svg',
        'zip',
        'gz',
        '7z',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum upload size in bytes (PRD §15.2)
    |--------------------------------------------------------------------------
    */

    'max_size' => 25 * 1024 * 1024, // 25 MB

];
