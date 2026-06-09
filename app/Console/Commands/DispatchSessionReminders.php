<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contexts\Scheduling\Domain\HijriDate;
use App\Contexts\Scheduling\Infrastructure\Notifications\LiveSessionReminderNotification;
use App\Contexts\Scheduling\Infrastructure\Persistence\LiveSession;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * Sends reminders for live sessions starting within the lead window
 * (default 30 min), once per session, to its registered learners — each in
 * their own timezone (PRD §5.و). Scheduled every minute.
 */
final class DispatchSessionReminders extends Command
{
    protected $signature = 'scheduling:dispatch-reminders {--lead=30 : Minutes before start}';

    protected $description = 'Dispatch reminders for upcoming live sessions';

    public function handle(): int
    {
        $lead = (int) $this->option('lead');
        $now = Date::now();
        $window = $now->copy()->addMinutes($lead);

        $sessions = LiveSession::query()
            ->whereNull('reminded_at')
            ->whereBetween('starts_at', [$now, $window])
            ->with('registrations')
            ->get();

        foreach ($sessions as $session) {
            $userIds = $session->registrations->pluck('user_id')->all();

            foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
                $tz = $user->timezone ?? 'Asia/Riyadh';
                $local = $session->starts_at->copy()->setTimezone($tz)->format('Y-m-d H:i');
                $hijri = HijriDate::format($session->starts_at, $tz);

                $user->notify(new LiveSessionReminderNotification(
                    $session->title,
                    "{$local} (هـ {$hijri})",
                    $session->join_url,
                ));
            }

            $session->update(['reminded_at' => $now]);
        }

        $this->info("Reminded {$sessions->count()} session(s).");

        return self::SUCCESS;
    }
}
