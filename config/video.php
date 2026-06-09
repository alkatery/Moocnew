<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Signed playback lifetime
    |--------------------------------------------------------------------------
    | How long (seconds) a signed playback URL stays valid. Short-lived by
    | design (PRD §8: signed media access).
    */

    'signed_ttl' => (int) env('VIDEO_SIGNED_TTL', 7200),

    /*
    |--------------------------------------------------------------------------
    | Bunny Stream (managed)
    |--------------------------------------------------------------------------
    | Token-authenticated embed playback. Secrets live in env only.
    */

    'bunny' => [
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'token_key' => env('BUNNY_STREAM_TOKEN_KEY'),
        'embed_host' => env('BUNNY_STREAM_EMBED_HOST', 'iframe.mediadelivery.net'),
        // Shared secret used to verify inbound status webhooks.
        'webhook_secret' => env('BUNNY_STREAM_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Self-hosted storage (S3-compatible, e.g. Cloudflare R2)
    |--------------------------------------------------------------------------
    | Delivered through a short-lived Laravel signed route, so it works with
    | any disk (local in dev, R2/S3 in production) without a separate video
    | service bill.
    */

    'storage' => [
        'disk' => env('VIDEO_STORAGE_DISK', 'media'),
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube (embed)
    |--------------------------------------------------------------------------
    */

    'youtube' => [
        'embed_host' => env('YOUTUBE_EMBED_HOST', 'www.youtube.com'),
    ],

];
