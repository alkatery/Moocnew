<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Application;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Engagement\Infrastructure\Persistence\CourseSurvey;
use App\Models\User;

/**
 * NELC learner-satisfaction surveys. A learner answers once per completed
 * course (resubmitting updates the answer); staff read aggregate averages
 * only — comments are returned without author identity (PDPL).
 */
final class SurveyService
{
    /**
     * @param  array{overall:int, content_quality:int, instructor_quality:int, platform_quality:int, comment?:string|null}  $answers
     */
    public function submit(User $user, int $courseId, array $answers): CourseSurvey
    {
        return CourseSurvey::query()->updateOrCreate(
            ['course_id' => $courseId, 'user_id' => $user->getKey()],
            [
                'overall' => $answers['overall'],
                'content_quality' => $answers['content_quality'],
                'instructor_quality' => $answers['instructor_quality'],
                'platform_quality' => $answers['platform_quality'],
                'comment' => $answers['comment'] ?? null,
            ],
        );
    }

    /**
     * Aggregate summary for one course: per-axis averages, response count
     * and the latest 20 comments stripped of any author identity (PDPL).
     *
     * @return array{averages: array<string, float>, count: int, comments: list<array{comment: string, created_at: string|null}>}
     */
    public function summaryFor(int $courseId): array
    {
        $aggregate = CourseSurvey::query()
            ->where('course_id', $courseId)
            ->selectRaw('count(*) as responses')
            ->selectRaw('avg(overall) as overall')
            ->selectRaw('avg(content_quality) as content_quality')
            ->selectRaw('avg(instructor_quality) as instructor_quality')
            ->selectRaw('avg(platform_quality) as platform_quality')
            ->first();

        $comments = CourseSurvey::query()
            ->where('course_id', $courseId)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->latest('id')
            ->limit(20)
            ->get(['comment', 'created_at'])
            ->map(fn (CourseSurvey $s): array => [
                'comment' => (string) $s->comment,
                'created_at' => $s->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'averages' => [
                'overall' => round((float) ($aggregate->overall ?? 0), 2),
                'content_quality' => round((float) ($aggregate->content_quality ?? 0), 2),
                'instructor_quality' => round((float) ($aggregate->instructor_quality ?? 0), 2),
                'platform_quality' => round((float) ($aggregate->platform_quality ?? 0), 2),
            ],
            'count' => (int) ($aggregate->responses ?? 0),
            'comments' => $comments,
        ];
    }

    /**
     * Per-course averages for every published course — powers the admin
     * «جودة التعليم» dashboard.
     *
     * @return list<array{course_id: int, title: string, slug: string, count: int, averages: array<string, float>}>
     */
    public function perCourseSummaries(): array
    {
        $stats = CourseSurvey::query()
            ->groupBy('course_id')
            ->selectRaw('course_id, count(*) as responses')
            ->selectRaw('avg(overall) as overall')
            ->selectRaw('avg(content_quality) as content_quality')
            ->selectRaw('avg(instructor_quality) as instructor_quality')
            ->selectRaw('avg(platform_quality) as platform_quality')
            ->get()
            ->keyBy('course_id');

        return Course::query()
            ->where('status', CourseStatus::Published->value)
            ->orderBy('title')
            ->get(['id', 'title', 'slug'])
            ->map(function (Course $course) use ($stats): array {
                $row = $stats->get($course->id);

                return [
                    'course_id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'count' => (int) ($row->responses ?? 0),
                    'averages' => [
                        'overall' => round((float) ($row->overall ?? 0), 2),
                        'content_quality' => round((float) ($row->content_quality ?? 0), 2),
                        'instructor_quality' => round((float) ($row->instructor_quality ?? 0), 2),
                        'platform_quality' => round((float) ($row->platform_quality ?? 0), 2),
                    ],
                ];
            })
            ->all();
    }
}
