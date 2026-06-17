<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Application;

use App\Contexts\Engagement\Domain\PointRule;
use App\Contexts\Engagement\Infrastructure\Persistence\Badge;
use App\Contexts\Engagement\Infrastructure\Persistence\LearnerStat;
use App\Contexts\Engagement\Infrastructure\Persistence\PointAward;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The gamification engine: idempotent point awards, a learning-day streak,
 * and milestone badges. Awards are funnelled through {@see award()} so the
 * streak and badge checks always run together.
 */
final class GamificationService
{
    /**
     * Award points for an action. `sourceKey` makes the award idempotent —
     * re-completing the same lesson never grants points twice. Returns the
     * learner's refreshed stats.
     */
    public function award(int $userId, PointRule $rule, ?string $sourceKey = null): LearnerStat
    {
        return DB::transaction(function () use ($userId, $rule, $sourceKey): LearnerStat {
            $stat = LearnerStat::query()->lockForUpdate()->firstOrCreate(['user_id' => $userId]);

            if ($sourceKey !== null) {
                $already = PointAward::query()
                    ->where('user_id', $userId)
                    ->where('source_key', $sourceKey)
                    ->exists();

                if ($already) {
                    return $stat;
                }
            }

            PointAward::query()->create([
                'user_id' => $userId,
                'reason' => $rule->value,
                'points' => $rule->points(),
                'source_key' => $sourceKey,
            ]);

            $stat->points += $rule->points();
            $this->touchStreak($stat);
            $stat->save();

            $this->awardBadges($stat);

            return $stat->refresh();
        });
    }

    /**
     * Advance the learning-day streak: +1 if the last active day was
     * yesterday, reset to 1 if there was a gap, unchanged if already today.
     */
    private function touchStreak(LearnerStat $stat): void
    {
        $today = Date::now()->toDateString();
        $last = $stat->last_active_on?->toDateString();

        if ($last === $today) {
            return;
        }

        $yesterday = Date::now()->subDay()->toDateString();
        $stat->current_streak = ($last === $yesterday) ? $stat->current_streak + 1 : 1;
        $stat->longest_streak = max($stat->longest_streak, $stat->current_streak);
        $stat->last_active_on = $today;
    }

    private function awardBadges(LearnerStat $stat): void
    {
        $earned = [];

        $completedCourses = PointAward::query()
            ->where('user_id', $stat->user_id)
            ->where('reason', PointRule::CourseCompleted->value)
            ->count();

        if ($completedCourses >= 1) {
            $earned[] = 'first_course';
        }
        if ($completedCourses >= 5) {
            $earned[] = 'courses_5';
        }
        if ($stat->current_streak >= 7) {
            $earned[] = 'streak_7';
        }
        if ($stat->current_streak >= 30) {
            $earned[] = 'streak_30';
        }
        if ($stat->points >= 1000) {
            $earned[] = 'points_1000';
        }

        foreach ($earned as $badge) {
            Badge::query()->firstOrCreate(
                ['user_id' => $stat->user_id, 'badge' => $badge],
                ['earned_at' => Date::now()],
            );
        }
    }
}
