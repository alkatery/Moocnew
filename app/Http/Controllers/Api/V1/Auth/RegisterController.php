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

        $token = $user->createToken(
            $request->input('device_name', 'web'),
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }
}
