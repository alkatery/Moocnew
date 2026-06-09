<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Commerce / Payments
    |--------------------------------------------------------------------------
    |
    | The platform ships in "free mode": every course enrolls directly and
    | the Commerce bounded context (orders, payments, invoices, ledger,
    | payouts) stays dormant. A Super Admin flips the `payments.enabled`
    | setting to switch the platform into paid mode without a redeploy.
    |
    | The runtime source of truth is the `settings` table (cached in Redis);
    | the value below only seeds the default before an admin has ever
    | toggled it. See ADR-0004 and PRD §1.
    |
    */

    'payments' => [
        'enabled_default' => (bool) env('PAYMENTS_ENABLED', false),
    ],

];
