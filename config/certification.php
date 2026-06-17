<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Certificate storage disk
    |--------------------------------------------------------------------------
    | Private disk where generated certificate PDFs are stored. Holders
    | download via an authenticated endpoint; the public can only verify
    | authenticity by UUID (PRD §5.ح).
    */

    'disk' => env('CERTIFICATE_DISK', 'local'),

];
