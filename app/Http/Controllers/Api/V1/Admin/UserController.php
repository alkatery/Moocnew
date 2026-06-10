<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Identity\Application\ActivityLogger;
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
}
