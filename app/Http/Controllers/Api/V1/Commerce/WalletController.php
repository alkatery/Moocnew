<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Contexts\Commerce\Application\LedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An instructor's available earnings balance, computed from the ledger.
 */
final class WalletController extends Controller
{
    public function show(Request $request, LedgerService $ledger): JsonResponse
    {
        $currency = (string) config('commerce.currency', 'SAR');
        $balance = $ledger->instructorBalance($request->user()->getKey(), $currency);

        return response()->json([
            'balance_minor' => $balance->minor,
            'currency' => $balance->currency,
            'minimum_payout_minor' => (int) config('commerce.minimum_payout_minor', 10000),
        ]);
    }
}
