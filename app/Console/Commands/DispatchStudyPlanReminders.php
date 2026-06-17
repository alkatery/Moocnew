<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contexts\Learning\Application\StudyPlanService;
use App\Contexts\Learning\Domain\StudyPlanStatus;
use App\Contexts\Learning\Infrastructure\Notifications\StudyPlanReminderNotification;
use App\Contexts\Learning\Infrastructure\Persistence\StudyPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * Sends the recurring study-plan nudges: each active plan is reminded once
 * per its cadence (in days) until completed. Scheduled hourly; idempotent
 * within a cadence window via last_reminded_at.
 */
final class DispatchStudyPlanReminders extends Command
{
    protected $signature = 'learning:dispatch-plan-reminders';

    protected $description = 'Dispatch recurring reminders for active study plans';

    public function handle(StudyPlanService $plans): int
    {
        $now = Date::now();
        $reminded = 0;

        StudyPlan::query()
            ->where('status', StudyPlanStatus::Active->value)
            ->with(['user', 'items.course'])
            ->chunkById(100, function ($chunk) use ($plans, $now, &$reminded): void {
                foreach ($chunk as $plan) {
                    $due = $plan->last_reminded_at === null
                        || $plan->last_reminded_at->copy()->addDays($plan->cadence_days)->lessThanOrEqualTo($now);

                    if (! $due || $plan->user === null) {
                        continue;
                    }

                    $progress = $plans->progress($plan);

                    if ($progress['total'] === 0) {
                        continue;
                    }

                    $nextTitle = $progress['next_course_id'] !== null
                        ? $plan->items->firstWhere('course_id', $progress['next_course_id'])?->course?->title
                        : null;

                    $plan->user->notify(new StudyPlanReminderNotification(
                        $plan->title,
                        $progress['percent'],
                        $progress['total'] - $progress['completed'],
                        $nextTitle,
                    ));

                    $plan->update(['last_reminded_at' => $now]);
                    $reminded++;
                }
            });

        $this->info("Reminded {$reminded} plan(s).");

        return self::SUCCESS;
    }
}
