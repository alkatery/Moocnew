<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contexts\Platform\Application\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the Commerce context behind the payments feature flag (PRD §1).
 * When payments are disabled the platform runs in free mode and Commerce
 * endpoints are invisible — they respond 404 as if they did not exist.
 *
 * Gating at request time (rather than skipping route registration) keeps
 * route caching intact and lets an admin toggle the flag without a redeploy.
 */
final class EnsurePaymentsEnabled
{
    public function __construct(
        private readonly FeatureFlags $features,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->features->paymentsEnabled(), 404);

        return $next($request);
    }
}
