<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Application;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Domain\Course\InvalidCourseTransition;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Drives the course publishing workflow (PRD §4): an instructor submits a
 * draft for review, and a supervisor either publishes it or sends it back.
 * Every transition is validated against {@see CourseStatus} and recorded
 * in the audit trail.
 */
final class CoursePublishing
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function submitForReview(Course $course, User $actor): Course
    {
        return $this->transition($course, CourseStatus::PendingReview, $actor, 'course.submitted');
    }

    public function approve(Course $course, User $actor): Course
    {
        $course = $this->transition($course, CourseStatus::Published, $actor, 'course.published');

        return $course;
    }

    public function reject(Course $course, User $actor): Course
    {
        return $this->transition($course, CourseStatus::Draft, $actor, 'course.rejected');
    }

    private function transition(Course $course, CourseStatus $target, User $actor, string $event): Course
    {
        if (! $course->status->canTransitionTo($target)) {
            throw InvalidCourseTransition::between($course->status, $target);
        }

        $course->status = $target;
        $course->published_at = $target === CourseStatus::Published ? Date::now() : null;
        $course->save();

        $this->activity->log($event, $actor, $course);

        return $course;
    }
}
