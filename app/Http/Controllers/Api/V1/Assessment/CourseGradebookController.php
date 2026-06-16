<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Application\GradebookService;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مصفوفة الدرجات لطاقم المقرر (عقد C1).
 *
 * متحكّم رفيع: يتحقّق من التخويل، يُهيّئ الترقيم، يُفوِّض للخدمة،
 * ويُعيد JSON بالشكل المتعاقد عليه.
 */
final class CourseGradebookController extends Controller
{
    public function __invoke(
        Request $request,
        Course $course,
        CourseAccess $access,
        GradebookService $gradebook,
    ): JsonResponse {
        // التخويل: طاقم المقرر فقط (مالك / courses.review / super_admin عبر Gate::before)
        abort_unless($access->isStaffFor($request->user(), $course), 403);

        // التحقّق من مُدخلات الترقيم (per_page 1–100، الافتراضي 50)
        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);

        // جلب الطلاب الملتحقين مرتبين (استعلام واحد مع pagination)
        ['items' => $students, 'total' => $total] = $gradebook->enrolledStudents($course, $page, $perPage);

        $userIds = $students->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        // بناء المصفوفة دُفعةً بلا N+1
        ['columns' => $columns, 'rows' => $rows] = $gradebook->for($course, $userIds);

        // ترتيب الصفوف بالاسم ثم user_id — مطابق لترتيب enrolledStudents
        // (الترتيب محفوظ من ترتيب $userIds المُمرَّر)

        $data = [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'passing_grade' => (int) ($course->passing_grade ?? 0),
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];

        // meta الترقيم (يظهر دائماً وفق العقد)
        $lastPage = (int) ceil($total / $perPage);
        $meta = [
            'current_page' => $page,
            'last_page' => max(1, $lastPage),
            'per_page' => $perPage,
            'total' => $total,
        ];

        return response()->json(['data' => $data, 'meta' => $meta]);
    }
}
