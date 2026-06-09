<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Domain;

/**
 * The kinds of notification the platform sends (PRD §5.ط). Each type can be
 * toggled per channel in the user's preference centre.
 */
enum NotificationType: string
{
    case EnrollmentConfirmed = 'enrollment_confirmed';
    case CourseCompleted = 'course_completed';
    case AssignmentGraded = 'assignment_graded';

    public function label(): string
    {
        return match ($this) {
            self::EnrollmentConfirmed => 'تأكيد الالتحاق',
            self::CourseCompleted => 'إتمام الدورة',
            self::AssignmentGraded => 'تصحيح الواجب',
        };
    }
}
