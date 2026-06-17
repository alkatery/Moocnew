<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    /** أقصى عدد محاولات دخول فاشلة لكل (بريد+IP) قبل الحظر المؤقّت. */
    private const MAX_ATTEMPTS = 5;

    /** مدّة احتساب المحاولات (ثوانٍ) — نافذة الحظر بعد تجاوز الحدّ. */
    private const DECAY_SECONDS = 60;

    public function __invoke(LoginRequest $request): JsonResponse
    {
        // حدّ معدّل بمفتاح (البريد+IP) — يصدّ القوّة الغاشمة، والأهمّ أنّه
        // يُفحص قبل Hash::check فيمنع استنزاف المعالج بـ bcrypt تحت الهجوم
        // (كلّ محاولة bcrypt مكلفة عمداً). نمط Fortify المعياري.
        $throttleKey = Str::transliterate(
            Str::lower($request->validated('email')).'|'.$request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["محاولات دخول كثيرة. حاول مرّة أخرى بعد {$seconds} ثانية."],
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            // A single generic message avoids leaking which factor failed.
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        if ($user->isDisabled()) {
            throw ValidationException::withMessages([
                'email' => ['هذا الحساب موقوف. تواصل مع الإدارة.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            // No token until the email is proven. The client surfaces a
            // resend action keyed off this code.
            return response()->json([
                'message' => 'يلزم تفعيل بريدك الإلكتروني قبل الدخول.',
                'code' => 'email_unverified',
            ], 403);
        }

        // مصادقة ناجحة — صفّر عدّاد المحاولات لهذا المفتاح.
        RateLimiter::clear($throttleKey);

        $token = $user->createToken(
            $request->input('device_name', 'web'),
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
