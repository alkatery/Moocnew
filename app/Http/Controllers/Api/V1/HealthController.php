<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contexts\Platform\Application\FeatureFlags;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Liveness/feature-discovery endpoint for the API. Returns the service
 * status alongside the currently-active feature flags so that the Next.js
 * frontend can decide, at runtime, whether to render Commerce surfaces.
 */
final class HealthController extends Controller
{
    public function __invoke(FeatureFlags $features): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'features' => [
                'payments_enabled' => $features->paymentsEnabled(),
            ],
        ]);
    }
}
