<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Identity\Application\AnonymizeUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Self-service account management for the authenticated user: profile edits,
 * password change, and the PDPL data-subject rights (export & erasure).
 */
final class AccountController extends Controller
{
    /** Update the editable profile fields the user owns. */
    public function update(UpdateAccountRequest $request, ActivityLogger $activity): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->validated())->save();
        $activity->log('user.profile_updated', $user, $user);

        return response()->json(['user' => new UserResource($user)]);
    }

    /** Change the password and revoke every other session. */
    public function password(ChangePasswordRequest $request, ActivityLogger $activity): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['password' => $request->validated('password')])->save();

        // Keep the current token; drop the rest so a leaked old session dies.
        $currentId = $request->user()->currentAccessToken()?->getKey();
        $user->tokens()->where('id', '!=', $currentId)->delete();

        $activity->log('user.password_changed', $user, $user);

        return response()->json(['message' => 'تم تحديث كلمة المرور.']);
    }

    /** PDPL right of access: a machine-readable copy of the user's data. */
    public function export(Request $request, ActivityLogger $activity): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $consents = $user->consents()->get()->map(fn ($c): array => [
            'type' => $c->type->value,
            'policy_version' => $c->policy_version,
            'consented_at' => $c->consented_at?->toIso8601String(),
        ]);

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with('course:id,title')
            ->get()
            ->map(fn (Enrollment $e): array => [
                'course' => $e->course?->title,
                'status' => $e->status->value,
                'progress_percent' => $e->progress_percent,
                'access_expires_at' => $e->access_expires_at?->toIso8601String(),
                'enrolled_at' => $e->created_at?->toIso8601String(),
            ]);

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->with(['course:id,title', 'path:id,title'])
            ->get()
            ->map(fn (Certificate $c): array => [
                'subject' => $c->subjectTitle(),
                'type' => $c->learning_path_id !== null ? 'learning_path' : 'course',
                'grade' => $c->grade,
                'issued_at' => $c->issued_at?->toIso8601String(),
                'verification_uuid' => $c->verification_uuid,
            ]);

        $activity->log('user.data_exported', $user, $user);

        return response()->json([
            'data' => [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'education_level' => $user->education_level,
                    'interests' => $user->interests ?? [],
                    'locale' => $user->locale,
                    'timezone' => $user->timezone,
                    'created_at' => $user->created_at?->toIso8601String(),
                ],
                'consents' => $consents,
                'enrollments' => $enrollments,
                'certificates' => $certificates,
            ],
        ])->withHeaders([
            'Content-Disposition' => 'attachment; filename="my-data.json"',
        ]);
    }

    /** PDPL right of erasure: anonymise the account (confirmed by password). */
    public function destroy(Request $request, AnonymizeUser $anonymize): Response
    {
        $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $anonymize->handle($user, 'user.self_deleted');

        return response()->noContent();
    }
}
