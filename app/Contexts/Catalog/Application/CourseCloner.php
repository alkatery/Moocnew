<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Application;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Shared\Application\SlugGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deep-copies a course for reuse in a new term: structure (sections,
 * lessons), assessments (question bank, quizzes with their question links,
 * assignments) — but never learner data (enrollments, attempts,
 * submissions). The copy starts life as an unpublished draft.
 */
final class CourseCloner
{
    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly ActivityLogger $activity,
    ) {}

    public function clone(User $actor, Course $course): Course
    {
        return DB::transaction(function () use ($actor, $course): Course {
            $title = "نسخة من {$course->title}";

            $copy = Course::query()->create([
                'instructor_id' => $course->instructor_id,
                'category_id' => $course->category_id,
                'title' => $title,
                'slug' => $this->slugs->forTitle($title, 'courses'),
                'summary' => $course->summary,
                'cover_image' => $course->cover_image,
                'description' => $course->description,
                'status' => CourseStatus::Draft,
                'pricing_type' => $course->pricing_type,
                'price_minor' => $course->price_minor,
                'passing_grade' => $course->passing_grade,
                'published_at' => null,
            ]);

            $sectionMap = $this->cloneCurriculum($course, $copy);
            $questionMap = $this->cloneQuestionBank($course, $copy);
            $this->cloneQuizzes($course, $copy, $sectionMap, $questionMap);
            $this->cloneAssignments($course, $copy, $sectionMap);

            $this->activity->log('course.cloned', $actor, $copy, [
                'source_course_id' => $course->getKey(),
            ]);

            return $copy;
        });
    }

    /**
     * @return array<int, int> old section id => new section id
     */
    private function cloneCurriculum(Course $source, Course $copy): array
    {
        $map = [];

        foreach ($source->sections()->with('lessons')->get() as $section) {
            $newSection = Section::query()->create([
                'course_id' => $copy->getKey(),
                'title' => $section->title,
                'position' => $section->position,
            ]);

            $map[$section->getKey()] = $newSection->getKey();

            foreach ($section->lessons as $lesson) {
                Lesson::query()->create([
                    'section_id' => $newSection->getKey(),
                    'title' => $lesson->title,
                    'type' => $lesson->type,
                    'content' => $lesson->content,
                    'transcript' => $lesson->transcript,
                    'asset_path' => $lesson->asset_path,
                    'video_provider' => $lesson->video_provider,
                    'video_id' => $lesson->video_id,
                    'video_status' => $lesson->video_status,
                    'position' => $lesson->position,
                    'is_free_preview' => $lesson->is_free_preview,
                ]);
            }
        }

        return $map;
    }

    /**
     * @return array<int, int> old question id => new question id
     */
    private function cloneQuestionBank(Course $source, Course $copy): array
    {
        $map = [];

        foreach (Question::query()->where('course_id', $source->getKey())->get() as $question) {
            $newQuestion = Question::query()->create([
                'course_id' => $copy->getKey(),
                'type' => $question->type,
                'body' => $question->body,
                'choices' => $question->choices,
                'correct' => $question->correct,
                'points' => $question->points,
            ]);

            $map[$question->getKey()] = $newQuestion->getKey();
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $sectionMap
     * @param  array<int, int>  $questionMap
     */
    private function cloneQuizzes(Course $source, Course $copy, array $sectionMap, array $questionMap): void
    {
        $quizzes = Quiz::query()
            ->where('course_id', $source->getKey())
            ->with('questions')
            ->get();

        foreach ($quizzes as $quiz) {
            $newQuiz = Quiz::query()->create([
                'course_id' => $copy->getKey(),
                'section_id' => $quiz->section_id !== null ? ($sectionMap[$quiz->section_id] ?? null) : null,
                'title' => $quiz->title,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'shuffle' => $quiz->shuffle,
                'max_attempts' => $quiz->max_attempts,
                'pass_mark' => $quiz->pass_mark,
                'weight' => $quiz->weight,
            ]);

            $links = [];

            foreach ($quiz->questions as $question) {
                $newQuestionId = $questionMap[$question->getKey()] ?? null;

                if ($newQuestionId !== null) {
                    $links[$newQuestionId] = ['position' => $question->pivot->position];
                }
            }

            $newQuiz->questions()->sync($links);
        }
    }

    /**
     * @param  array<int, int>  $sectionMap
     */
    private function cloneAssignments(Course $source, Course $copy, array $sectionMap): void
    {
        foreach (Assignment::query()->where('course_id', $source->getKey())->get() as $assignment) {
            Assignment::query()->create([
                'course_id' => $copy->getKey(),
                'section_id' => $assignment->section_id !== null ? ($sectionMap[$assignment->section_id] ?? null) : null,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'due_at' => $assignment->due_at,
                'points' => $assignment->points,
                'weight' => $assignment->weight,
            ]);
        }
    }
}
