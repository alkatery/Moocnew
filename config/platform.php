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

    /*
    |--------------------------------------------------------------------------
    | PDPL
    |--------------------------------------------------------------------------
    |
    | The version string stamped on every consent record (PRD §5.أ, §7).
    | Bump it whenever the privacy/data-processing policy changes so that
    | consent history stays auditable and re-consent can be detected.
    |
    */

    'pdpl' => [
        'policy_version' => env('PDPL_POLICY_VERSION', '2026-06-01'),
    ],

];
