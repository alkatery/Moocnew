<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | The SPA frontend is served from a different origin/port than the API
    | (e.g. :3000 vs :8080), so the browser issues cross-origin requests.
    | Authentication uses Sanctum *bearer tokens* (Authorization header), not
    | cookies, so credentials are not required and any origin may be allowed.
    | Tighten `allowed_origins` to your domain(s) for production if desired.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', '*')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
