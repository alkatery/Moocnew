<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Grading;

/**
 * Supplies a learner's overall grade in a course (0–100), computed from the
 * course's assessments. Implemented by the Assessment context and consumed
 * by Enrollment's completion logic — keeping the two contexts decoupled: a
 * course "completes" only when its lessons are done AND the learner meets
 * the course's passing grade (the Edraak model).
 */
interface CourseGradeProvider
{
    /**
     * The learner's overall course grade as a percentage, or null when the
     * course has no graded assessments (in which case completion is gated on
     * lessons alone).
     */
    public function gradeFor(int $userId, int $courseId): ?int;
}
