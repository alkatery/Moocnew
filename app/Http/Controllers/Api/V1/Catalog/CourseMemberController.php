<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إدارة فريق التأليف الجماعي للمقرر — E3.
 *
 * كل الأفعال مقيّدة بـ manageMembers (المالك فقط + super_admin عبر Gate::before).
 * co-author لا يدير الأعضاء (منع التصعيد).
 * PDPL: الاستجابات تكشف id/name فقط — لا بريد ولا هاتف.
 */
final class CourseMemberController extends Controller
{
    /**
     * قائمة المؤلّفين المشاركين للمقرر.
     *
     * GET /api/v1/catalog/courses/{course}/members
     * التخويل: manageMembers (المالك فقط).
     * استجابة 200: قائمة { id, name, role }.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manageMembers', $course), 403);

        $members = $course->members()
            ->select(['users.id', 'users.name'])
            ->withPivot('role')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->pivot->role,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $members]);
    }

    /**
     * إضافة مؤلّف مشارك للمقرر.
     *
     * POST /api/v1/catalog/courses/{course}/members
     * body: { user_id: int }
     * التخويل: manageMembers (المالك فقط).
     * 422 — غير مدرّس / المالك نفسه.
     * 200 — مكرّر (idempotent).
     * 201 — مضاف ناجح.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manageMembers', $course), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $userId = (int) $data['user_id'];

        // المالك لا يُضاف لنفسه — هو مالك بالخاصيّة instructor_id.
        if ($userId === $course->instructor_id) {
            return response()->json([
                'message' => 'المالك مؤلّف أصلاً ولا يُضاف كمؤلّف مشارك.',
                'errors' => ['user_id' => ['المالك مؤلّف أصلاً ولا يُضاف كمؤلّف مشارك.']],
            ], 422);
        }

        /** @var User $target */
        $target = User::query()->findOrFail($userId);

        // يجب أن يكون المستخدم مدرّساً (hasRole أو can('courses.manage')).
        if (! $target->hasRole(Role::Instructor->value) && ! $target->can('courses.manage')) {
            return response()->json([
                'message' => 'المستخدم المحدّد ليس مدرّساً.',
                'errors' => ['user_id' => ['المستخدم المحدّد ليس مدرّساً.']],
            ], 422);
        }

        // فحص التكرار — idempotent: إن كان موجوداً يُعاد 200 بالقائمة الحالية.
        $alreadyMember = $course->members()->where('users.id', $userId)->exists();

        if (! $alreadyMember) {
            $course->members()->attach($userId, ['role' => 'co_author']);
        }

        return response()->json(
            ['data' => $this->buildMemberList($course)],
            $alreadyMember ? 200 : 201,
        );
    }

    /**
     * إزالة مؤلّف مشارك من المقرر.
     *
     * DELETE /api/v1/catalog/courses/{course}/members/{user}
     * التخويل: manageMembers (المالك فقط).
     * 204 — تمّت الإزالة أو لم يكن عضواً (idempotent).
     */
    public function destroy(Request $request, Course $course, User $user): JsonResponse
    {
        abort_unless($request->user()->can('manageMembers', $course), 403);

        // detach بلا شرط — idempotent.
        $course->members()->detach($user->getKey());

        return response()->json(status: 204);
    }

    /**
     * بحث المدرّسين لـ picker الواجهة (PDPL: id/name فقط).
     *
     * GET /api/v1/catalog/courses/{course}/instructors?q=...
     * التخويل: manageMembers (مرتبط بمقرر يملكه — لا بحث مفتوح).
     * يستثني: المالك + الأعضاء الحاليين.
     * نتائج محدودة: 10.
     */
    public function searchInstructors(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('manageMembers', $course), 403);

        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $term = trim((string) $request->query('q', ''));

        // معرّفات المستخدمين المستثناءين: المالك + الأعضاء الحاليون.
        $excludedIds = $course->members()
            ->pluck('users.id')
            ->push($course->instructor_id)
            ->unique()
            ->all();

        $instructors = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::Instructor->value))
            ->whereNotIn('id', $excludedIds)
            ->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%"))
            ->select(['id', 'name'])          // PDPL: id/name فقط، لا بريد في الخرج.
            ->limit(10)
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();

        return response()->json(['data' => $instructors]);
    }

    /**
     * بناء قائمة الأعضاء المحدّثة للإعادة في store.
     *
     * @return list<array{id: int, name: string, role: string}>
     */
    private function buildMemberList(Course $course): array
    {
        return $course->members()
            ->select(['users.id', 'users.name'])
            ->withPivot('role')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->pivot->role,
            ])
            ->values()
            ->all();
    }
}
