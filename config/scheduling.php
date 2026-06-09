<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Live session providers (PRD §5.و)
    |--------------------------------------------------------------------------
    | Credentials for the managed meeting providers. When absent, those
    | providers throw on use; the "manual" provider (paste a link) always
    | works and needs no credentials.
    */

    'zoom' => [
        'account_token' => env('ZOOM_ACCOUNT_TOKEN'),
    ],

    'google_meet' => [
        'access_token' => env('GOOGLE_MEET_ACCESS_TOKEN'),
    ],

    // Lead time (minutes) for live-session reminders.
    'reminder_lead_minutes' => (int) env('SCHEDULING_REMINDER_LEAD', 30),

];
