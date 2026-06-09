<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes here are prefixed with `/api/v1` (configured in
| bootstrap/app.php). Bounded contexts register their own route groups as
| they come online; the Commerce group will only be registered when the
| payments feature flag is enabled (PRD §1).
|
*/

Route::get('health', HealthController::class)->name('api.health');
