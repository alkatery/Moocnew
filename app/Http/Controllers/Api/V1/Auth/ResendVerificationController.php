<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Re-sends the verification mail for accounts that have not yet confirmed.
 * The response is always generic so the endpoint never reveals whether an
 * email is registered (account-enumeration safe).
 */
final class ResendVerificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'إن كان البريد مسجّلاً وغير مفعّل، أرسلنا إليه رابط تفعيل.',
        ]);
    }
}
