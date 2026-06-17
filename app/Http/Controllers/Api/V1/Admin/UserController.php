<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Identity\Application\AnonymizeUser;
use App\Contexts\Identity\Application\CreateUserAccount;
use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Identity\Domain\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Admin user management (PRD §4): onboarding instructors/supervisors,
 * adjusting roles, and impersonating any learner or instructor to perform
 * their actions or troubleshoot. Gated by users.manage (Super Admin).
 */
final class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);

        $term = trim((string) $request->query('q', ''));
        $role = $request->query('role');

        $users = User::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")))
            ->when(is_string($role) && $role !== '', fn ($q) => $q->whereHas(
                'roles',
                fn ($r) => $r->where('name', $role),
            ))
            ->with('roles')
            ->latest()
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request, CreateUserAccount $accounts): JsonResponse
    {
        $user = $accounts->handle(
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
            Role::from($request->validated('role')),
        );

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        ActivityLogger $activity,
    ): UserResource {
        // The platform role is single-valued here; swapping it replaces any
        // existing platform role. A Super Admin's role is never altered.
        abort_if($user->hasRole(Role::SuperAdmin->value), 403, 'لا يمكن تعديل دور حساب الإدارة العليا.');

        $role = Role::from($request->validated('role'));
        $user->syncRoles([$role->value]);

        $activity->log('user.role_changed', $request->user(), $user, [
            'role' => $role->value,
        ]);

        return new UserResource($user->load('roles'));
    }

    /**
     * Issue a fresh access token for the target user so an admin can browse
     * the platform as them. Refused for Super Admin targets to prevent
     * privilege juggling; fully audited.
     */
    public function impersonate(Request $request, User $user, ActivityLogger $activity): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageUsers->value), 403);
        abort_if($user->is($actor), 422, 'لا يمكنك انتحال حسابك نفسه.');
        abort_if($user->hasRole(Role::SuperAdmin->value), 403, 'لا يمكن الدخول إلى حساب إدارة عليا.');

        $token = $user->createToken("impersonation:by:{$actor->getKey()}")->plainTextToken;

        $activity->log('user.impersonated', $actor, $user);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * Suspend or restore an account. A disabled user cannot authenticate;
     * a Super Admin can never be disabled.
     */
    public function updateStatus(Request $request, User $user, ActivityLogger $activity): UserResource
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);
        abort_if($user->hasRole(Role::SuperAdmin->value), 403, 'لا يمكن إيقاف حساب الإدارة العليا.');

        $disabled = $request->boolean('disabled');
        $user->forceFill(['disabled_at' => $disabled ? Date::now() : null])->save();

        if ($disabled) {
            // Revoke active sessions so the suspension takes effect at once.
            $user->tokens()->delete();
        }

        $activity->log($disabled ? 'user.disabled' : 'user.enabled', $request->user(), $user);

        return new UserResource($user->load('roles'));
    }

    /**
     * Set a new password and return it once so the admin can hand it over.
     */
    public function resetPassword(Request $request, User $user, ActivityLogger $activity): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);
        abort_if($user->hasRole(Role::SuperAdmin->value), 403, 'لا يمكن إعادة تعيين كلمة مرور الإدارة العليا.');

        $password = Str::password(12);
        $user->forceFill(['password' => $password])->save();
        $user->tokens()->delete();

        $activity->log('user.password_reset', $request->user(), $user);

        return response()->json(['data' => ['password' => $password]]);
    }

    public function destroy(Request $request, User $user, ActivityLogger $activity): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);
        abort_if($user->is($request->user()), 422, 'لا يمكنك حذف حسابك.');
        abort_if($user->hasRole(Role::SuperAdmin->value), 403, 'لا يمكن حذف حساب الإدارة العليا.');

        $user->tokens()->delete();
        $user->delete(); // soft delete

        $activity->log('user.deleted', $request->user(), $user);

        return response()->json(status: 204);
    }

    /**
     * PDPL «right to erasure» on behalf of a data subject: anonymise the
     * account (clear PII, revoke sessions, soft-delete) while keeping its
     * related records referentially intact. Distinct from destroy(), which
     * keeps PII for recoverable removals.
     */
    public function retire(Request $request, User $user, AnonymizeUser $anonymize): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);
        abort_if($user->is($request->user()), 422, 'لا يمكنك إخفاء هوية حسابك.');
        abort_if($user->hasRole(Role::SuperAdmin->value), 403, 'لا يمكن إخفاء هوية حساب الإدارة العليا.');

        $anonymize->handle($user, 'user.retired', $request->user());

        return response()->json(status: 204);
    }

    /**
     * An instructor's courses with enrollment counts — lets staff review an
     * instructor's catalogue without impersonating them.
     */
    public function courses(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);

        $courses = Course::query()
            ->where('instructor_id', $user->getKey())
            ->withCount('enrollments')
            ->latest()
            ->get()
            ->map(fn (Course $c): array => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'status' => $c->status->value,
                'enrollments_count' => $c->enrollments_count,
            ]);

        return response()->json([
            'data' => [
                'instructor' => new UserResource($user->load('roles')),
                'courses' => $courses,
                'stats' => [
                    'courses' => $courses->count(),
                    'published' => $courses->where('status', CourseStatus::Published->value)->count(),
                    'learners' => (int) $courses->sum('enrollments_count'),
                ],
            ],
        ]);
    }

    /**
     * Reassign a course to a different instructor.
     */
    public function transferCourse(Request $request, Course $course, ActivityLogger $activity): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);

        $validated = $request->validate([
            'instructor_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newOwner = User::query()->findOrFail($validated['instructor_id']);
        abort_unless($newOwner->hasRole(Role::Instructor->value), 422, 'المستخدم المحدّد ليس مدرّساً.');
        abort_if($newOwner->isDisabled(), 422, 'لا يمكن نقل الدورة إلى حساب موقوف.');

        $course->update(['instructor_id' => $newOwner->getKey()]);

        $activity->log('course.transferred', $request->user(), $course, [
            'instructor_id' => $newOwner->getKey(),
        ]);

        return response()->json(['data' => ['instructor_id' => $newOwner->getKey()]]);
    }
}
