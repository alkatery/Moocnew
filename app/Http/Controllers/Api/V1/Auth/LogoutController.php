<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // Revoke only the token presented on this request, leaving the
        // user's other devices signed in.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(status: 204);
    }
}
