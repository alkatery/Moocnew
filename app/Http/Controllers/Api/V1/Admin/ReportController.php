<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Domain\Grading\CourseGradeProvider;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports for staff (analytics.view): enrollments with progress and
 * grades, and a per-course performance summary. Responses are streamed and
 * start with a UTF-8 BOM so Arabic text opens correctly in Excel.
 */
final class ReportController extends Controller
{
    private const BOM = "\xEF\xBB\xBF";

    public function enrollments(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can(Permission::ViewAnalytics->value), 403);

        $validated = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'status' => ['nullable', Rule::enum(EnrollmentStatus::class)],
        ]);

        $query = Enrollment::query()
            ->with(['user', 'course'])
            ->when(isset($validated['course_id']), fn ($q) => $q->where('course_id', (int) $validated['course_id']))
            ->when(isset($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->orderBy('id');

        $grades = app(CourseGradeProvider::class);

        return $this->csv('enrollments.csv', function ($out) use ($query, $grades): void {
            fputcsv($out, ['student_name', 'email', 'course', 'status', 'progress_percent', 'grade', 'enrolled_at', 'completed_at'], escape: '\\');

            $query->chunk(200, function ($enrollments) use ($out, $grades): void {
                foreach ($enrollments as $enrollment) {
                    fputcsv($out, [
                        $enrollment->user?->name,
                        $enrollment->user?->email,
                        $enrollment->course?->title,
                        $enrollment->status->value,
                        $enrollment->progress_percent,
                        $grades->gradeFor($enrollment->user_id, $enrollment->course_id),
                        $enrollment->enrolled_at?->toDateTimeString(),
                        $enrollment->completed_at?->toDateTimeString(),
                    ], escape: '\\');
                }
            });
        });
    }

    public function courses(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can(Permission::ViewAnalytics->value), 403);

        $courses = Course::query()
            ->where('status', CourseStatus::Published->value)
            ->with('instructor')
            ->withCount([
                'enrollments',
                'enrollments as completed_count' => fn ($q) => $q->where('status', EnrollmentStatus::Completed->value),
                'reviews',
            ])
            ->withAvg('enrollments as avg_progress', 'progress_percent')
            ->withAvg('reviews', 'rating')
            ->orderBy('id');

        return $this->csv('courses.csv', function ($out) use ($courses): void {
            fputcsv($out, ['title', 'instructor', 'enrollments', 'completed', 'avg_progress', 'avg_rating', 'reviews_count'], escape: '\\');

            foreach ($courses->get() as $course) {
                fputcsv($out, [
                    $course->title,
                    $course->instructor?->name,
                    $course->enrollments_count,
                    $course->completed_count,
                    $course->avg_progress !== null ? round((float) $course->avg_progress, 1) : 0,
                    $course->reviews_avg_rating !== null ? round((float) $course->reviews_avg_rating, 1) : null,
                    $course->reviews_count,
                ], escape: '\\');
            }
        });
    }

    /**
     * @param  callable(resource): void  $writer
     */
    private function csv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, self::BOM);
            $writer($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
