<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Application;

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * خدمة استيراد الأسئلة (E5 — مكتبات المحتوى).
 * تنسخ أسئلة من بنوك مقررات المؤلّف الأخرى إلى بنك المقرر الهدف
 * نسخاً عميقة مستقلّة تماماً (لا FK ولا مرجع للمصدر).
 */
final class QuestionImporter
{
    public function __construct(
        private readonly CourseAccess $access,
    ) {}

    /**
     * استيراد أسئلة مصدر إلى بنك المقرر الهدف.
     *
     * @param  int[]  $sourceQuestionIds
     * @return Question[]
     *
     * @throws HttpException
     */
    public function import(User $actor, Course $targetCourse, array $sourceQuestionIds): array
    {
        return DB::transaction(function () use ($actor, $targetCourse, $sourceQuestionIds): array {
            // جلب الأسئلة مع مقرراتها في استعلام واحد (تفادي N+1).
            $sources = Question::query()
                ->whereIn('id', $sourceQuestionIds)
                ->with('course')
                ->get()
                ->keyBy('id');

            // التحقّق من اكتمال المعرّفات المطلوبة.
            $missing = array_diff($sourceQuestionIds, $sources->keys()->all());
            if ($missing !== []) {
                abort(422, 'some_questions_not_found');
            }

            // التحقّق من ملكية كل مصدر قبل أي إدراج (ذرّية كاملة).
            foreach ($sourceQuestionIds as $id) {
                $source = $sources->get($id);

                // منع الاستيراد من البنك نفسه.
                if ($source->course_id === $targetCourse->getKey()) {
                    abort(422, 'cannot_import_into_self');
                }

                // عزل الملكية: يجب أن يكون المستخدم طاقماً على مقرر المصدر.
                if (! $this->access->isStaffFor($actor, $source->course)) {
                    abort(403, 'لا تملك صلاحية استيراد هذا السؤال.');
                }
            }

            // النسخ العميق: صفوف جديدة تماماً بكل الحقول السبعة — بلا أي مرجع للمصدر.
            $copies = [];
            foreach ($sourceQuestionIds as $id) {
                $source = $sources->get($id);

                $copies[] = Question::query()->create([
                    'course_id' => $targetCourse->getKey(),
                    'type' => $source->type,
                    'body' => $source->body,
                    'choices' => $source->choices,
                    'correct' => $source->correct,
                    'config' => $source->config,
                    'explanation' => $source->explanation,
                    'points' => $source->points,
                ]);
            }

            return $copies;
        });
    }
}
