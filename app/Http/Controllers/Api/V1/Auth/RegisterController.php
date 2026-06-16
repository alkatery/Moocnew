<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Contexts\Identity\Application\RegisterUser;
use App\Contexts\Identity\Application\RegisterUserData;
use App\Contexts\Identity\Domain\Consent\ConsentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $consents = array_map(
            static fn (string $type): ConsentType => ConsentType::from($type),
            array_unique($request->validated('consents')),
        );

        $user = $registerUser->handle(new RegisterUserData(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            consents: $consents,
            profile: $request->safe()->only([
                'phone',
                'country',
                'education_level',
                'interests',
                'locale',
                'timezone',
            ]),
        ));

        // Email ownership must be proven before a token is ever issued
        // (login blocks unverified accounts). The verification mail is a
        // transactional security notification, so it bypasses the
        // preference centre intentionally.
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'تم إنشاء حسابك. أرسلنا رابط تفعيل إلى بريدك.',
            'user' => new UserResource($user),
        ], 201);
    }
}
