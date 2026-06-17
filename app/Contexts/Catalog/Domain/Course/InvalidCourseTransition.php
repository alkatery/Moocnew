<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Raised when a course is asked to move between two statuses that the
 * lifecycle does not allow (e.g. publishing a draft without review).
 */
final class InvalidCourseTransition extends DomainException
{
    private const LABELS = [
        'draft' => 'مسودّة',
        'pending_review' => 'قيد المراجعة',
        'published' => 'منشورة',
    ];

    public static function between(CourseStatus $from, CourseStatus $to): self
    {
        $fromLabel = self::LABELS[$from->value] ?? $from->value;
        $toLabel = self::LABELS[$to->value] ?? $to->value;

        return new self("لا يمكن نقل الدورة من حالة «{$fromLabel}» إلى «{$toLabel}».");
    }

    /**
     * Render as a clean 422 (validation-style) response rather than a 500,
     * so the client shows the Arabic reason instead of "Server Error".
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
