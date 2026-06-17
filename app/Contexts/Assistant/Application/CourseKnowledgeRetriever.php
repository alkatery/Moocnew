<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use Illuminate\Support\Str;

/**
 * Retrieves the lesson passages most relevant to a learner's question — the
 * grounding for the tutor (both the rules answer and the Claude context).
 *
 * v1 uses keyword overlap over lesson titles/content (works offline, no
 * embeddings service); the interface is intentionally simple so it can be
 * swapped for pgvector semantic search later without touching callers.
 */
final class CourseKnowledgeRetriever
{
    /**
     * @return list<array{title: string, snippet: string}>
     */
    public function retrieve(Course $course, string $question, int $limit = 3): array
    {
        $lessons = Lesson::query()
            ->whereHas('section', fn ($q) => $q->where('course_id', $course->getKey()))
            ->get(['title', 'content']);

        $terms = $this->terms($question);

        $scored = $lessons
            ->map(function (Lesson $lesson) use ($terms): array {
                $haystack = Str::lower(($lesson->title ?? '').' '.($lesson->content ?? ''));
                $score = 0;
                foreach ($terms as $term) {
                    $score += substr_count($haystack, $term);
                }

                return [
                    'title' => (string) $lesson->title,
                    'snippet' => Str::limit(trim((string) ($lesson->content ?: $lesson->title)), 400),
                    'score' => $score,
                ];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        // Fall back to the course summary when nothing matches, so the
        // assistant still has something to ground its answer in.
        if ($scored->isEmpty()) {
            return [[
                'title' => $course->title,
                'snippet' => Str::limit((string) ($course->description ?: $course->summary ?: $course->title), 400),
            ]];
        }

        return $scored->map(fn (array $r): array => [
            'title' => $r['title'],
            'snippet' => $r['snippet'],
        ])->all();
    }

    /**
     * @return list<string>
     */
    private function terms(string $question): array
    {
        $clean = Str::lower(preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]/u', ' ', $question) ?? $question);
        $words = array_filter(
            preg_split('/\s+/u', $clean) ?: [],
            static fn (string $w): bool => Str::length($w) >= 3,
        );

        return array_values(array_unique($words));
    }
}
