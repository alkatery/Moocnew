<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Application;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use Illuminate\Support\Collection;

/**
 * مصفوفة درجات كل طلاب المقرر (عرض طاقم المقرر) — عقد C1.
 *
 * تُحمَّل كلّ بيانات التقييم دُفعةً واحدة لتجنّب N+1:
 *   — اختبارات المقرر مرة واحدة.
 *   — واجبات المقرر مرة واحدة.
 *   — أفضل محاولة لكل (quiz,user) باستعلام مجمّع واحد (groupBy+MAX).
 *   — آخر تسليم مُصحَّح لكل (assignment,user) باستعلام واحد.
 *   — الطلاب مع أسمائهم باستعلام واحد (join على users).
 *   — بناء الصفوف في الذاكرة (صفر استعلام إضافي).
 *
 * صيغة المتوسط الموزون مُفوَّضة إلى CourseGradeService::weightedOverall (DRY §2).
 */
final class GradebookService
{
    /**
     * بناء مصفوفة الدرجات لمجموعة طلاب.
     *
     * @param  list<int>  $userIds  معرّفات الطلاب الملتحقين (مُرتَّبة من enrolledStudents)
     * @return array{columns: list<array<string,mixed>>, rows: list<array<string,mixed>>}
     */
    public function for(Course $course, array $userIds): array
    {
        // ── 1. تحميل عناصر التقييم مرة واحدة ─────────────────────────────
        /** @var Collection<int,Quiz> $quizzes */
        $quizzes = Quiz::query()
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get(['id', 'title', 'pass_mark', 'weight']);

        /** @var Collection<int,Assignment> $assignments */
        $assignments = Assignment::query()
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get(['id', 'title', 'points', 'weight']);

        // ── 2. بناء أعمدة الجدول (الاختبارات ثم الواجبات، بترتيب id) ─────
        $columns = [];

        foreach ($quizzes as $quiz) {
            $columns[] = [
                'key' => "quiz:{$quiz->id}",
                'type' => 'quiz',
                'id' => $quiz->id,
                'title' => $quiz->title,
                'pass_mark' => $quiz->pass_mark,
                'weight' => max(1, (int) $quiz->weight),
            ];
        }

        foreach ($assignments as $assignment) {
            $columns[] = [
                'key' => "assignment:{$assignment->id}",
                'type' => 'assignment',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'pass_mark' => null,
                'weight' => max(1, (int) $assignment->weight),
            ];
        }

        // مقرر بلا تقييمات → أعمدة فارغة
        if ($userIds === [] || $columns === []) {
            return [
                'columns' => $columns,
                'rows' => $this->buildRowsWithoutGrades($course, $userIds),
            ];
        }

        $quizIds = $quizzes->pluck('id')->all();
        $assignmentIds = $assignments->pluck('id')->all();

        // ── 3. أفضل محاولة مُقدَّمة لكل (quiz_id, user_id) — استعلام واحد ─
        // [quizId][userId] => best_score
        /** @var array<int,array<int,int>> $bestAttempts */
        $bestAttempts = [];

        if ($quizIds !== []) {
            QuizAttempt::query()
                ->whereIn('quiz_id', $quizIds)
                ->whereIn('user_id', $userIds)
                ->whereNotNull('submitted_at')
                ->selectRaw('quiz_id, user_id, MAX(score) as best')
                ->groupBy('quiz_id', 'user_id')
                ->get()
                ->each(function ($row) use (&$bestAttempts): void {
                    $bestAttempts[(int) $row->quiz_id][(int) $row->user_id] = (int) $row->best;
                });
        }

        // ── 4. آخر تسليم مُصحَّح لكل (assignment_id, user_id) — استعلام واحد
        // [assignmentId][userId] => grade
        /** @var array<int,array<int,int>> $gradedSubmissions */
        $gradedSubmissions = [];

        if ($assignmentIds !== []) {
            // نأخذ أول قيمة لكل (assignment,user) اتساقاً مع ->first() في CourseGradeService
            AssignmentSubmission::query()
                ->whereIn('assignment_id', $assignmentIds)
                ->whereIn('user_id', $userIds)
                ->whereNotNull('graded_at')
                ->get(['assignment_id', 'user_id', 'grade'])
                ->each(function ($row) use (&$gradedSubmissions): void {
                    $aId = (int) $row->assignment_id;
                    $uId = (int) $row->user_id;
                    if (! isset($gradedSubmissions[$aId][$uId])) {
                        $gradedSubmissions[$aId][$uId] = (int) $row->grade;
                    }
                });
        }

        // ── 5. تحميل بيانات الطلاب دفعةً — استعلام واحد مع join ──────────
        $studentMap = $this->loadStudentData($course->id, $userIds);

        // ── 6. بناء الصفوف في الذاكرة (صفر استعلام إضافي) ────────────────
        $rows = [];

        foreach ($userIds as $userId) {
            $student = $studentMap[$userId] ?? null;
            $cells = [];
            $components = []; // لحساب overall مطابقاً لـ CourseGradeService::gradeFor

            // خلايا الاختبارات
            foreach ($quizzes as $quiz) {
                $key = "quiz:{$quiz->id}";
                $weight = max(1, (int) $quiz->weight);
                $passMark = (int) $quiz->pass_mark;
                $score = isset($bestAttempts[$quiz->id][$userId])
                    ? $bestAttempts[$quiz->id][$userId]
                    : null;

                $cells[$key] = [
                    'score' => $score,
                    'passed' => $score !== null && $score >= $passMark,
                ];

                // الصفر للغائب يدخل في المتوسط الموزون (مطابق CourseGradeService)
                $components[] = ['score' => (int) ($score ?? 0), 'weight' => $weight];
            }

            // خلايا الواجبات
            foreach ($assignments as $assignment) {
                $key = "assignment:{$assignment->id}";
                $weight = max(1, (int) $assignment->weight);
                $points = max(1, (int) $assignment->points);

                $rawGrade = $gradedSubmissions[$assignment->id][$userId] ?? null;
                $score = $rawGrade !== null
                    ? max(0, min(100, (int) round(($rawGrade / $points) * 100)))
                    : null;

                $cells[$key] = [
                    'score' => $score,
                    'passed' => $score !== null, // لا حد نجاح للواجب
                ];

                $components[] = ['score' => (int) ($score ?? 0), 'weight' => $weight];
            }

            // الدرجة الكلية الموزونة — مُفوَّضة إلى CourseGradeService (DRY)
            $overall = CourseGradeService::weightedOverall($components);
            $bar = (int) ($course->passing_grade ?? 0);

            $rows[] = [
                'user_id' => $userId,
                'name' => $student['name'] ?? '',
                'enrollment_status' => $student['status'] ?? EnrollmentStatus::Active->value,
                'overall' => $overall,
                'passed' => $bar <= 0 || $overall === null || $overall >= $bar,
                'cells' => $cells,
            ];
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * قائمة الطلاب الملتحقين (active+completed، نافذة غير منتهية)
     * مرتبةً بالاسم تصاعدياً ثم user_id (مفكّك تعادل حتمي).
     *
     * @return array{items: Collection<int,object>, total: int}
     */
    public function enrolledStudents(Course $course, int $page, int $perPage): array
    {
        // نستخدم toBase() لتجنّب cast الـ enum في Enrollment ونحصل على قيم خام
        $query = Enrollment::query()
            ->toBase()
            ->where('enrollments.course_id', $course->id)
            ->whereIn('enrollments.status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->where(fn ($q) => $q->whereNull('enrollments.access_expires_at')->orWhere('enrollments.access_expires_at', '>', now()))
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->orderBy('enrollments.user_id')
            ->select(['enrollments.user_id', 'enrollments.status', 'users.name']);

        $total = (clone $query)->count();

        $items = $query
            ->forPage($page, $perPage)
            ->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * تحميل (الاسم + حالة الالتحاق) لمجموعة طلاب — استعلام واحد مع join.
     * لا يُكشف أي PII غير الاسم والحالة (PDPL).
     *
     * @param  list<int>  $userIds
     * @return array<int, array{name: string, status: string}>
     */
    private function loadStudentData(int $courseId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        // toBase() يتجنّب cast الـ enum في موديل Enrollment ويعطينا قيماً خاماً
        $rows = Enrollment::query()
            ->toBase()
            ->where('enrollments.course_id', $courseId)
            ->whereIn('enrollments.user_id', $userIds)
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->select(['enrollments.user_id', 'enrollments.status', 'users.name'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->user_id] = [
                'name' => (string) $row->name,
                'status' => (string) $row->status,
            ];
        }

        return $map;
    }

    /**
     * صفوف بلا درجات — لمقرر بلا أعمدة تقييم أو لقائمة طلاب فارغة.
     * overall = null وpassed = true إن لم يكن للمقرر حد نجاح.
     *
     * @param  list<int>  $userIds
     * @return list<array<string,mixed>>
     */
    private function buildRowsWithoutGrades(Course $course, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $studentMap = $this->loadStudentData($course->id, $userIds);
        $rows = [];

        foreach ($userIds as $userId) {
            $student = $studentMap[$userId] ?? null;
            $rows[] = [
                'user_id' => $userId,
                'name' => $student['name'] ?? '',
                'enrollment_status' => $student['status'] ?? EnrollmentStatus::Active->value,
                'overall' => null,
                // بلا تقييمات = لا يمكن الرسوب (passed: true دائماً) — §3 العقد
                'passed' => true,
                'cells' => (object) [], // {} في JSON
            ];
        }

        return $rows;
    }
}
