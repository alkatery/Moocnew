<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Live-session reminders (PRD §5.و) — runs every minute, idempotent per session.
Schedule::command('scheduling:dispatch-reminders')->everyMinute()->withoutOverlapping();

// Study-plan nudges — hourly; each plan is reminded once per its cadence.
Schedule::command('learning:dispatch-plan-reminders')->hourly()->withoutOverlapping();
