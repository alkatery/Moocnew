<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Application\QuestionImporter;
use App\Contexts\Assessment\Domain\QuestionType;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\ImportQuestionsRequest;
use App\Http\Resources\ImportableQuestionResource;
use App\Http\Resources\QuestionAdminResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * E5 — مكتبات المحتوى: استيراد أسئلة بين مقررات المؤلّف.
 */
final class QuestionImportController extends Controller
{
    public function __construct(
        private readonly QuestionImporter $importer,
    ) {}

    /**
     * GET /api/v1/assessment/courses/{course}/questions/importable
     * قائمة أسئلة المؤلّف القابلة للاستيراد إلى بنك {course} الهدف.
     */
    public function importable(Request $request, Course $course): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('update', $course), 403);

        // التحقّق من بارامترات الاستعلام الاختيارية.
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'source_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'type' => ['nullable', Rule::enum(QuestionType::class)],
        ]);

        $user = $request->user();

        // جمع معرّفات مقررات الطاقم (مالك أو co_author أو reviewer) باستثناء الهدف.
        $staffCourseIds = Course::query()
            ->unless(
                $user->can(Permission::ReviewCourses->value),
                fn ($q) => $q->where(fn ($w) => $w
                    ->where('instructor_id', $user->getKey())
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->getKey()))),
            )
            ->where('id', '!=', $course->getKey())
            ->pluck('id');

        $query = Question::query()
            ->whereIn('course_id', $staffCourseIds)
            ->with('course:id,title,slug')
            ->latest();

        // تصفية بحث نصّي في body.
        if ($q = $request->query('q')) {
            $query->where('body', 'like', '%'.$q.'%');
        }

        // تصفية بنوع السؤال.
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // تصفية بمقرر مصدر بعينه — لكن فقط إن كان ضمن مقررات الطاقم (أمان).
        if ($sourceCourseId = $request->query('source_course_id')) {
            $query->where('course_id', $sourceCourseId);
        }

        return ImportableQuestionResource::collection($query->paginate(20));
    }

    /**
     * POST /api/v1/assessment/courses/{course}/questions/import
     * نسخ عميق ذرّي لأسئلة مختارة إلى بنك {course} الهدف.
     */
    public function import(ImportQuestionsRequest $request, Course $course): JsonResponse
    {
        $copies = $this->importer->import(
            $request->user(),
            $course,
            array_map('intval', $request->validated('source_question_ids')),
        );

        return QuestionAdminResource::collection(collect($copies))
            ->response()
            ->setStatusCode(201);
    }
}
