<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Models\User;

/**
 * بوّابة الوحدة (#3): الوحدة تُقفَل على المتعلّم إن كانت أيّ وحدة سابقة
 * (بترتيب أقل) تحمل اختبار «بوّابة» لم يجتَزه بعد. اجتياز اختبار الوحدة يفتح
 * ما يليها. الطاقم لا يخضع للقفل (يتولّاه المُستدعي).
 */
final class SectionGate
{
    /**
     * معرّفات الوحدات المقفلة على المستخدم في هذا المقرر.
     *
     * @return list<int>
     */
    public function lockedSectionIds(User $user, Course $course): array
    {
        // [section_id => gate_quiz_id] لكل وحدة فيها اختبار بوّابة.
        $gates = Quiz::query()
            ->where('course_id', $course->getKey())
            ->where('is_gate', true)
            ->whereNotNull('section_id')
            ->pluck('id', 'section_id');

        if ($gates->isEmpty()) {
            return [];
        }

        // اختبارات البوّابة التي اجتازها المستخدم.
        $passed = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('passed', true)
            ->whereIn('quiz_id', $gates->values())
            ->pluck('quiz_id')
            ->unique()
            ->all();

        $sections = $course->sections()->orderBy('position')->orderBy('id')->get(['id']);

        $locked = [];
        $blocked = false;
        foreach ($sections as $section) {
            if ($blocked) {
                $locked[] = (int) $section->id;
            }

            // إن كانت هذه الوحدة تحمل بوّابة لم تُجتَز، تُقفَل كل ما بعدها.
            $gateQuizId = $gates[$section->id] ?? null;
            if ($gateQuizId !== null && ! in_array($gateQuizId, $passed, true)) {
                $blocked = true;
            }
        }

        return $locked;
    }

    /** هل الوحدة مفتوحة للمستخدم (لم تقفلها بوّابة وحدة سابقة)؟ */
    public function isUnlockedFor(User $user, Section $section): bool
    {
        return ! in_array((int) $section->id, $this->lockedSectionIds($user, $section->course), true);
    }

    /**
     * هل القسم المُعطى (بمعرّفه، وقد يكون null لتقييم عام للمقرر) مفتوح للمستخدم؟
     * تقييمات على مستوى المقرر (section_id = null) غير مُبوّبة.
     */
    public function isSectionUnlockedFor(User $user, Course $course, ?int $sectionId): bool
    {
        if ($sectionId === null) {
            return true;
        }

        return ! in_array($sectionId, $this->lockedSectionIds($user, $course), true);
    }
}
