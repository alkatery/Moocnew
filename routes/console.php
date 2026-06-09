<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Live-session reminders (PRD §5.و) — runs every minute, idempotent per session.
Schedule::command('scheduling:dispatch-reminders')->everyMinute()->withoutOverlapping();
