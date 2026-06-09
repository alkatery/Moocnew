<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\PresenceController;
use App\Http\Controllers\Api\V1\Assessment\AssignmentController;
use App\Http\Controllers\Api\V1\Assessment\AssignmentSubmissionController;
use App\Http\Controllers\Api\V1\Assessment\QuestionController;
use App\Http\Controllers\Api\V1\Assessment\QuizAttemptController;
use App\Http\Controllers\Api\V1\Assessment\QuizController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\CourseController;
use App\Http\Controllers\Api\V1\Catalog\CoursePublishingController;
use App\Http\Controllers\Api\V1\Catalog\LessonController;
use App\Http\Controllers\Api\V1\Catalog\SectionController;
use App\Http\Controllers\Api\V1\Certification\CertificateController;
use App\Http\Controllers\Api\V1\Communication\ForumController;
use App\Http\Controllers\Api\V1\Communication\TicketController;
use App\Http\Controllers\Api\V1\Enrollment\EnrollmentController;
use App\Http\Controllers\Api\V1\Enrollment\LessonProgressController;
use App\Http\Controllers\Api\V1\Enrollment\MediaStreamController;
use App\Http\Controllers\Api\V1\Enrollment\PlaybackController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Notification\PreferenceController;
use App\Http\Controllers\Api\V1\Scheduling\CalendarController;
use App\Http\Controllers\Api\V1\Scheduling\LiveSessionController;
use App\Http\Controllers\Api\V1\Webhooks\BunnyVideoWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\MoyasarPaymentWebhookController;
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

/*
|--------------------------------------------------------------------------
| Enrollment & Access (PRD §5.ج)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->name('api.enrollment.')->group(function () {
    Route::post('catalog/courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('enroll');
    Route::get('enrollments', [EnrollmentController::class, 'index'])->name('index');

    Route::post('lessons/{lesson}/progress', [LessonProgressController::class, 'store'])->name('progress');
    Route::get('lessons/{lesson}/playback', [PlaybackController::class, 'show'])->name('playback');
});

// Signed, short-lived delivery of storage-hosted videos (no auth: the
// signature is the capability).
Route::get('media/stream/{lesson}', MediaStreamController::class)
    ->middleware('signed')
    ->name('api.media.stream');

// Managed video provider status webhook (signature-verified, idempotent).
Route::post('webhooks/video/bunny', BunnyVideoWebhookController::class)->name('api.webhooks.video.bunny');

// Payment gateway webhook (Moyasar) — gated by payments.enabled, signed, idempotent.
Route::post('webhooks/payments/moyasar', MoyasarPaymentWebhookController::class)
    ->middleware('payments.enabled')
    ->name('api.webhooks.payments.moyasar');

/*
|--------------------------------------------------------------------------
| Assessment — quizzes & assignments (PRD §5.هـ)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('assessment')->name('api.assessment.')->group(function () {
    // Question bank (authoring).
    Route::get('courses/{course}/questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::post('courses/{course}/questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::patch('questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

    // Quizzes.
    Route::get('courses/{course}/quizzes', [QuizController::class, 'index'])->name('quizzes.index');
    Route::post('courses/{course}/quizzes', [QuizController::class, 'store'])->name('quizzes.store');
    Route::get('quizzes/{quiz}', [QuizController::class, 'show'])->name('quizzes.show');
    Route::patch('quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
    Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');

    // Attempts.
    Route::post('quizzes/{quiz}/attempts', [QuizAttemptController::class, 'start'])->name('attempts.start');
    Route::get('attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('attempts.show');
    Route::post('attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->name('attempts.submit');

    // Assignments.
    Route::get('courses/{course}/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::post('courses/{course}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::post('assignments/{assignment}/submissions', [AssignmentSubmissionController::class, 'store'])->name('submissions.store');
    Route::get('assignments/{assignment}/submissions', [AssignmentSubmissionController::class, 'index'])->name('submissions.index');
    Route::post('submissions/{submission}/grade', [AssignmentSubmissionController::class, 'grade'])->name('submissions.grade');
});

/*
|--------------------------------------------------------------------------
| Certificates (PRD §5.ح)
|--------------------------------------------------------------------------
*/
// Public verification (reached via the QR code on the certificate).
Route::get('certificates/verify/{uuid}', [CertificateController::class, 'verify'])->name('api.certificates.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('certificates', [CertificateController::class, 'index'])->name('api.certificates.index');
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])->name('api.certificates.download');
});

/*
|--------------------------------------------------------------------------
| Notifications & preference centre (PRD §5.ط)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('notifications')->name('api.notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
    Route::get('preferences', [PreferenceController::class, 'index'])->name('preferences.index');
    Route::put('preferences', [PreferenceController::class, 'update'])->name('preferences.update');
});

/*
|--------------------------------------------------------------------------
| Analytics & presence (PRD §5.ي) — aggregate only (PDPL)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('presence/heartbeat', [PresenceController::class, 'heartbeat'])->name('api.presence.heartbeat');
    Route::get('analytics/overview', [AnalyticsController::class, 'overview'])->name('api.analytics.overview');
    Route::get('analytics/courses/{course}/dropoff', [AnalyticsController::class, 'courseDropoff'])->name('api.analytics.dropoff');
});

/*
|--------------------------------------------------------------------------
| Scheduling — live sessions & calendar (PRD §5.و)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('scheduling')->name('api.scheduling.')->group(function () {
    Route::get('courses/{course}/sessions', [LiveSessionController::class, 'index'])->name('sessions.index');
    Route::post('courses/{course}/sessions', [LiveSessionController::class, 'store'])->name('sessions.store');
    Route::post('sessions/{session}/register', [LiveSessionController::class, 'register'])->name('sessions.register');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
});

/*
|--------------------------------------------------------------------------
| Communication — forums & support tickets (PRD §5.ز)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('community')->name('api.community.')->group(function () {
    // Forums.
    Route::get('courses/{course}/threads', [ForumController::class, 'index'])->name('threads.index');
    Route::post('courses/{course}/threads', [ForumController::class, 'storeThread'])->name('threads.store');
    Route::get('threads/{thread}', [ForumController::class, 'show'])->name('threads.show');
    Route::post('threads/{thread}/posts', [ForumController::class, 'reply'])->name('threads.reply');
    Route::post('posts/{post}/report', [ForumController::class, 'report'])->name('posts.report');
    Route::post('posts/{post}/hide', [ForumController::class, 'hide'])->name('posts.hide');
    Route::post('courses/{course}/bans', [ForumController::class, 'ban'])->name('bans.store');

    // Support tickets.
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/messages', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::post('tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
});
