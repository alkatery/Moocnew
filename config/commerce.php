<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Commerce (Phase 2, behind payments.enabled)
    |--------------------------------------------------------------------------
    | Settings for the Commerce context, active only when payments are
    | enabled (PRD §1). The payment aggregator is Moyasar (ADR-0004).
    */

    'currency' => env('COMMERCE_CURRENCY', 'SAR'),
    'gateway' => env('COMMERCE_GATEWAY', 'moyasar'),

    // Platform commission taken from each paid order; the remainder is
    // credited to the instructor's ledger account.
    'commission_percent' => (float) env('COMMERCE_COMMISSION_PERCENT', 20),

    // Minimum balance (minor units) an instructor must have to request a payout.
    'minimum_payout_minor' => (int) env('COMMERCE_MIN_PAYOUT_MINOR', 10000),

    'moyasar' => [
        'secret_key' => env('MOYASAR_SECRET_KEY'),
        'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
    ],

];
