<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Application\CertificateService;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Learning\Domain\PathEnrollmentStatus;
use App\Contexts\Learning\Domain\PathItemState;
use App\Contexts\Learning\Infrastructure\Notifications\PathCompletedNotification;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPathItem;
use App\Contexts\Learning\Infrastructure\Persistence\PathEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

/**
 * Learning-path progression: courses are taken strictly in (level, position)
 * order; an item unlocks only when everything before it is completed, and
 * finishing every item completes the path and issues a path certificate.
 */
final class PathProgressService
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly CertificateService $certificates,
    ) {}

    /**
     * The path's items in learning order, each carrying the viewer's state
     * (completed / unlocked / locked). Guests see every item locked except
     * the first, which is shown as unlocked to invite them in.
     *
     * @return list<array{item: LearningPathItem, state: PathItemState}>
     */
    public function itemsWithState(LearningPath $path, ?User $user): array
    {
        $items = $path->items()->with('course.instructor')->get();

        $completedCourseIds = $user === null
            ? collect()
            : Enrollment::query()
                ->where('user_id', $user->getKey())
                ->where('status', EnrollmentStatus::Completed->value)
                ->whereIn('course_id', $items->pluck('course_id'))
                ->pluck('course_id');

        $result = [];
        $previousAllCompleted = true;

        foreach ($items as $item) {
            $completed = $completedCourseIds->contains($item->course_id);

            $state = match (true) {
                $completed => PathItemState::Completed,
                $previousAllCompleted => PathItemState::Unlocked,
                default => PathItemState::Locked,
            };

            $result[] = ['item' => $item, 'state' => $state];
            $previousAllCompleted = $previousAllCompleted && $completed;
        }

        return $result;
    }

    /**
     * @return array{completed: int, total: int, percent: int}
     */
    public function progress(LearningPath $path, ?User $user): array
    {
        $states = $this->itemsWithState($path, $user);
        $total = count($states);
        $completed = count(array_filter($states, fn (array $row): bool => $row['state'] === PathItemState::Completed));

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) floor(($completed / $total) * 100) : 0,
        ];
    }

    public function joinPath(User $user, LearningPath $path): PathEnrollment
    {
        return PathEnrollment::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'learning_path_id' => $path->getKey(),
        ], [
            'status' => PathEnrollmentStatus::Active,
        ]);
    }

    /**
     * Enroll in one of the path's courses, enforcing the sequence: every
     * item before this course must already be completed.
     */
    public function enrollInCourse(User $user, LearningPath $path, Course $course): Enrollment
    {
        $states = $this->itemsWithState($path, $user);

        $row = collect($states)->first(
            fn (array $r): bool => $r['item']->course_id === $course->getKey(),
        );

        if ($row === null) {
            throw ValidationException::withMessages([
                'course' => 'هذه الدورة ليست ضمن المسار.',
            ]);
        }

        if ($row['state'] === PathItemState::Locked) {
            throw ValidationException::withMessages([
                'course' => 'أكمل الدورات السابقة في المسار أولاً — المسار يؤخذ بالترتيب.',
            ]);
        }

        $this->joinPath($user, $path);

        return $this->enrollments->enroll($user, $course);
    }

    /**
     * Called when a learner completes any course: completes every active
     * path membership whose items are now all done, and issues the path
     * certificate.
     */
    public function syncCompletionFor(User $user, int $courseId): void
    {
        $memberships = PathEnrollment::query()
            ->where('user_id', $user->getKey())
            ->where('status', PathEnrollmentStatus::Active->value)
            ->whereHas('path.items', fn ($q) => $q->where('course_id', $courseId))
            ->with('path')
            ->get();

        foreach ($memberships as $membership) {
            $progress = $this->progress($membership->path, $user);

            if ($progress['total'] === 0 || $progress['completed'] < $progress['total']) {
                continue;
            }

            $membership->update([
                'status' => PathEnrollmentStatus::Completed,
                'completed_at' => Date::now(),
            ]);

            $this->certificates->issueForPath($user->getKey(), $membership->learning_path_id);

            $user->notify(new PathCompletedNotification($membership->path->title));
        }
    }
}
