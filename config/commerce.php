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

];
