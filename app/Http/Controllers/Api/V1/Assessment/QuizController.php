<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreQuizRequest;
use App\Http\Resources\QuizResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class QuizController extends Controller
{
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        abort_unless(app(CourseAccess::class)->canParticipate($request->user(), $course), 403);

        return QuizResource::collection(
            Quiz::query()->where('course_id', $course->getKey())->withCount('questions')->get(),
        );
    }

    public function show(Request $request, Quiz $quiz, CourseAccess $access): QuizResource
    {
        $course = $quiz->course;
        abort_unless($access->canParticipate($request->user(), $course), 403);

        // Staff see the questions inline; learners start an attempt to get them.
        if ($access->isStaffFor($request->user(), $course)) {
            $quiz->load('questions');
        }

        return new QuizResource($quiz->loadCount('questions'));
    }

    public function store(StoreQuizRequest $request, Course $course): JsonResponse
    {
        $quiz = Quiz::query()->create([
            'course_id' => $course->getKey(),
            'section_id' => $request->validated('section_id'),
            'title' => $request->validated('title'),
            'time_limit_minutes' => $request->validated('time_limit_minutes'),
            'shuffle' => (bool) $request->validated('shuffle', true),
            'max_attempts' => $request->validated('max_attempts'),
            'pass_mark' => (int) $request->validated('pass_mark', 60),
            'weight' => (int) $request->validated('weight', 1),
        ]);

        $this->syncQuestions($quiz, $request->validated('question_ids'));

        return (new QuizResource($quiz->loadCount('questions')))->response()->setStatusCode(201);
    }

    public function update(StoreQuizRequest $request, Quiz $quiz): QuizResource
    {
        $quiz->update([
            'title' => $request->validated('title'),
            'section_id' => $request->validated('section_id', $quiz->section_id),
            'time_limit_minutes' => $request->validated('time_limit_minutes'),
            'shuffle' => (bool) $request->validated('shuffle', $quiz->shuffle),
            'max_attempts' => $request->validated('max_attempts'),
            'pass_mark' => (int) $request->validated('pass_mark', $quiz->pass_mark),
            'weight' => (int) $request->validated('weight', $quiz->weight),
        ]);

        $this->syncQuestions($quiz, $request->validated('question_ids'));

        return new QuizResource($quiz->loadCount('questions'));
    }

    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        abort_unless($request->user()->can('update', $quiz->course), 403);

        $quiz->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  list<int>  $questionIds
     */
    private function syncQuestions(Quiz $quiz, array $questionIds): void
    {
        $payload = [];
        foreach (array_values($questionIds) as $position => $id) {
            $payload[$id] = ['position' => $position + 1];
        }

        $quiz->questions()->sync($payload);
    }
}
