<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Confirms email ownership from a signed link. The `signed` + `throttle`
 * middleware authenticate the request (no Sanctum token needed yet); we
 * still match the hash against the stored email to be safe.
 */
final class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        if ($user === null
            || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'رابط التفعيل غير صالح.',
                'code' => 'invalid_verification',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->respond(
                $request,
                ['message' => 'بريدك مفعّل مسبقاً.', 'already' => true],
                'already',
            );
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return $this->respond($request, [
            'message' => 'تم تفعيل بريدك بنجاح.',
            'user' => new UserResource($user),
            'token' => $token,
        ], 'success');
    }

    /**
     * API clients (Accept: application/json) get JSON; a human clicking the
     * link in their mail client is redirected to the SPA landing page.
     *
     * @param  array<string, mixed>  $payload
     */
    private function respond(Request $request, array $payload, string $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away("{$frontend}/verify-email?status={$status}");
    }
}
