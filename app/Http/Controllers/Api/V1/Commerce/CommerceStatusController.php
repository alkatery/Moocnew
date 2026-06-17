<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Entry point of the Commerce context (PRD §1, §5.د). The full commerce
 * surface — orders, payments (Moyasar), coupons, the double-entry ledger,
 * payouts, refunds and ZATCA invoicing — is delivered in Phase 2 behind
 * this same flag. This status endpoint exists so the flag-gated wiring is
 * real and verifiable today: it is reachable only when payments are
 * enabled.
 */
final class CommerceStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'enabled' => true,
            'currency' => config('commerce.currency', 'SAR'),
            'gateway' => config('commerce.gateway', 'moyasar'),
        ]);
    }
}
