<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contexts\Platform\Application\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks assistant routes when the admin has set the mode to `off`. Returns
 * 404 so a disabled assistant is indistinguishable from a missing feature.
 */
final class EnsureAssistantEnabled
{
    public function __construct(
        private readonly FeatureFlags $flags,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->flags->assistantMode() === 'off', 404);

        return $next($request);
    }
}
