<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Domain\QuestionType;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreQuestionRequest;
use App\Http\Resources\QuestionAdminResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class QuestionController extends Controller
{
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('update', $course), 403);

        return QuestionAdminResource::collection(
            Question::query()->where('course_id', $course->getKey())->latest()->paginate(20),
        );
    }

    public function store(StoreQuestionRequest $request, Course $course): JsonResponse
    {
        $question = Question::query()->create([
            'course_id' => $course->getKey(),
            'type' => QuestionType::from($request->validated('type')),
            'body' => $request->validated('body'),
            'choices' => $request->validated('choices'),
            'correct' => $request->validated('correct'),
            'explanation' => $request->validated('explanation'),
            'points' => (int) $request->validated('points', 1),
        ]);

        return (new QuestionAdminResource($question))->response()->setStatusCode(201);
    }

    public function update(StoreQuestionRequest $request, Question $question): QuestionAdminResource
    {
        $question->update([
            'type' => QuestionType::from($request->validated('type')),
            'body' => $request->validated('body'),
            'choices' => $request->validated('choices'),
            'correct' => $request->validated('correct'),
            'explanation' => $request->validated('explanation', $question->explanation),
            'points' => (int) $request->validated('points', $question->points),
        ]);

        return new QuestionAdminResource($question);
    }

    public function destroy(Request $request, Question $question): JsonResponse
    {
        abort_unless($request->user()->can('update', $question->course), 403);

        $question->delete();

        return response()->json(status: 204);
    }
}
