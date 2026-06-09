<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Application;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Scheduling\Domain\HijriDate;
use App\Contexts\Scheduling\Infrastructure\Persistence\LiveSession;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A unified calendar (PRD §5.و) merging live sessions and assignment due
 * dates across the learner's active courses, each stamped with both
 * Gregorian and Hijri dates and the user's timezone.
 */
final class CalendarService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user, Carbon $from, Carbon $to): array
    {
        $courseIds = Enrollment::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->pluck('course_id')
            ->all();

        if ($courseIds === []) {
            return [];
        }

        $timezone = $user->timezone ?? 'Asia/Riyadh';
        $events = [];

        foreach (LiveSession::query()->whereIn('course_id', $courseIds)
            ->whereBetween('starts_at', [$from, $to])->get() as $session) {
            $events[] = $this->event('live_session', $session->title, $session->course_id, $session->starts_at, $timezone);
        }

        foreach (Assignment::query()->whereIn('course_id', $courseIds)
            ->whereNotNull('due_at')->whereBetween('due_at', [$from, $to])->get() as $assignment) {
            $events[] = $this->event('assignment_due', $assignment->title, $assignment->course_id, $assignment->due_at, $timezone);
        }

        usort($events, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $type, string $title, int $courseId, Carbon $at, string $timezone): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'course_id' => $courseId,
            'at' => $at->toIso8601String(),
            'at_local' => $at->copy()->setTimezone($timezone)->toIso8601String(),
            'hijri' => HijriDate::format($at, $timezone),
        ];
    }
}
