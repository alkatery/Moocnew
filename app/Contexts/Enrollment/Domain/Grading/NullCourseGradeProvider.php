<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Grading;

/**
 * Default grade provider used when no assessment implementation is bound:
 * every course is treated as having no graded assessments, so completion
 * stays gated on lessons alone. Overridden by the Assessment context.
 */
final class NullCourseGradeProvider implements CourseGradeProvider
{
    public function gradeFor(int $userId, int $courseId): ?int
    {
        return null;
    }
}
