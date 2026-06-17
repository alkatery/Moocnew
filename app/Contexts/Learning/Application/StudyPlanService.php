<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Application;

use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Learning\Domain\StudyPlanStatus;
use App\Contexts\Learning\Infrastructure\Notifications\StudyPlanCompletedNotification;
use App\Contexts\Learning\Infrastructure\Persistence\StudyPlan;
use App\Contexts\Learning\Infrastructure\Persistence\StudyPlanItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Personal study plans: progress is derived from the owner's course
 * enrollments; completing the last course closes the plan (and stops its
 * reminders).
 */
final class StudyPlanService
{
    /**
     * @param  list<int>  $courseIds
     */
    public function create(User $user, string $title, int $cadenceDays, ?string $targetDate, array $courseIds): StudyPlan
    {
        return DB::transaction(function () use ($user, $title, $cadenceDays, $targetDate, $courseIds): StudyPlan {
            $plan = StudyPlan::query()->create([
                'user_id' => $user->getKey(),
                'title' => $title,
                'cadence_days' => $cadenceDays,
                'target_date' => $targetDate,
                'status' => StudyPlanStatus::Active,
                // Start the reminder clock now: the first nudge arrives one
                // full cadence after creation, not immediately.
                'last_reminded_at' => Date::now(),
            ]);

            $this->syncItems($plan, $courseIds);

            return $plan;
        });
    }

    /**
     * @param  list<int>  $courseIds
     */
    public function syncItems(StudyPlan $plan, array $courseIds): void
    {
        $plan->items()->delete();

        foreach (array_values(array_unique($courseIds)) as $index => $courseId) {
            StudyPlanItem::query()->create([
                'study_plan_id' => $plan->getKey(),
                'course_id' => $courseId,
                'position' => $index + 1,
            ]);
        }
    }

    /**
     * The plan's items (with courses) plus whether the owner has completed
     * each course.
     *
     * @return Collection<int, array{item: StudyPlanItem, completed: bool}>
     */
    public function itemsWithCompletion(StudyPlan $plan): Collection
    {
        $items = $plan->items()->with('course')->get();

        $completedCourseIds = Enrollment::query()
            ->where('user_id', $plan->user_id)
            ->where('status', EnrollmentStatus::Completed->value)
            ->whereIn('course_id', $items->pluck('course_id'))
            ->pluck('course_id');

        return $items->map(fn (StudyPlanItem $item): array => [
            'item' => $item,
            'completed' => $completedCourseIds->contains($item->course_id),
        ])->values();
    }

    /**
     * @return array{completed: int, total: int, percent: int, next_course_id: int|null}
     */
    public function progress(StudyPlan $plan): array
    {
        $rows = $this->itemsWithCompletion($plan);

        $total = $rows->count();
        $completed = $rows->where('completed', true)->count();
        $next = $rows->firstWhere('completed', false);

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) floor(($completed / $total) * 100) : 0,
            'next_course_id' => $next !== null ? $next['item']->course_id : null,
        ];
    }

    /**
     * Called when a learner completes any course: closes every active plan
     * of theirs that is now fully done.
     */
    public function syncCompletionFor(User $user, int $courseId): void
    {
        $plans = StudyPlan::query()
            ->where('user_id', $user->getKey())
            ->where('status', StudyPlanStatus::Active->value)
            ->whereHas('items', fn ($q) => $q->where('course_id', $courseId))
            ->get();

        foreach ($plans as $plan) {
            $progress = $this->progress($plan);

            if ($progress['total'] === 0 || $progress['completed'] < $progress['total']) {
                continue;
            }

            $plan->update([
                'status' => StudyPlanStatus::Completed,
                'completed_at' => Date::now(),
            ]);

            $user->notify(new StudyPlanCompletedNotification($plan->title));
        }
    }
}
