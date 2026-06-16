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
    case SessionReminder = 'session_reminder';
    case PathCompleted = 'path_completed';
    case StudyPlanReminder = 'study_plan_reminder';
    case CourseAnnouncement = 'course_announcement';  // C3: إعلانات المقرر
    case CourseBulkEmail = 'course_bulk_email';        // C3: رسائل المعلّم الجماعية
    case ForumReply = 'forum_reply';                   // D2: ردود المنتدى
    case Digest = 'digest';                            // D3: ملخّص النشاط الدوري

    public function label(): string
    {
        return match ($this) {
            self::EnrollmentConfirmed => 'تأكيد الالتحاق',
            self::CourseCompleted => 'إتمام الدورة',
            self::AssignmentGraded => 'تصحيح الواجب',
            self::SessionReminder => 'تذكير بحصة مباشرة',
            self::PathCompleted => 'إتمام مسار تخصصي',
            self::StudyPlanReminder => 'تنبيهات الخطة الدراسية',
            self::CourseAnnouncement => 'إعلانات المقرر',
            self::CourseBulkEmail => 'رسائل المعلّم',
            self::ForumReply => 'ردود المنتدى',
            self::Digest => 'ملخّص النشاط',
        };
    }
}
