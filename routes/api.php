<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\CourseController;
use App\Http\Controllers\Api\V1\Catalog\CoursePublishingController;
use App\Http\Controllers\Api\V1\Catalog\LessonController;
use App\Http\Controllers\Api\V1\Catalog\SectionController;
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

Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('register', RegisterController::class)->name('register');
    Route::post('login', LoginController::class)->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', LogoutController::class)->name('logout');
        Route::get('me', MeController::class)->name('me');
    });
});

Route::middleware('auth:sanctum')->prefix('admin')->name('api.admin.')->group(function () {
    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::patch('settings/payments', [SettingsController::class, 'updatePayments'])
        ->name('settings.payments.update');
});

/*
|--------------------------------------------------------------------------
| Catalog (PRD §5.ب)
|--------------------------------------------------------------------------
| Public browsing of published courses; authoring and review require
| authentication and are gated by the CoursePolicy.
*/
Route::prefix('catalog')->name('api.catalog.')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');

        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::patch('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        // Publishing workflow.
        Route::post('courses/{course}/submit', [CoursePublishingController::class, 'submit'])->name('courses.submit');
        Route::post('courses/{course}/approve', [CoursePublishingController::class, 'approve'])->name('courses.approve');
        Route::post('courses/{course}/reject', [CoursePublishingController::class, 'reject'])->name('courses.reject');

        // Sections (shallow-nested under courses).
        Route::post('courses/{course}/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::patch('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

        // Lessons (shallow-nested under sections).
        Route::post('sections/{section}/lessons', [LessonController::class, 'store'])->name('lessons.store');
        Route::patch('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
    });
});
