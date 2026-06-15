<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AccountController;
use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\CourseCloneController;
use App\Http\Controllers\Api\V1\Admin\EnrollmentCodeController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\SiteContentController as AdminSiteContentController;
use App\Http\Controllers\Api\V1\Admin\SurveySummaryController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\UserImportController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Analytics\PresenceController;
use App\Http\Controllers\Api\V1\Assessment\AssignmentController;
use App\Http\Controllers\Api\V1\Assessment\AssignmentSubmissionController;
use App\Http\Controllers\Api\V1\Assessment\CourseGradeController;
use App\Http\Controllers\Api\V1\Assessment\QuestionController;
use App\Http\Controllers\Api\V1\Assessment\QuizAttemptController;
use App\Http\Controllers\Api\V1\Assessment\QuizController;
use App\Http\Controllers\Api\V1\Assistant\AnalystController;
use App\Http\Controllers\Api\V1\Assistant\TutorController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResendVerificationController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\CourseController;
use App\Http\Controllers\Api\V1\Catalog\CoursePublishingController;
use App\Http\Controllers\Api\V1\Catalog\LessonController;
use App\Http\Controllers\Api\V1\Catalog\LessonTranscriptController;
use App\Http\Controllers\Api\V1\Catalog\SectionController;
use App\Http\Controllers\Api\V1\Certification\CertificateController;
use App\Http\Controllers\Api\V1\Communication\ForumController;
use App\Http\Controllers\Api\V1\Communication\TicketController;
use App\Http\Controllers\Api\V1\Content\ContactController;
use App\Http\Controllers\Api\V1\Content\NewsController;
use App\Http\Controllers\Api\V1\Content\SiteContentController;
use App\Http\Controllers\Api\V1\Engagement\GamificationController;
use App\Http\Controllers\Api\V1\Engagement\ReviewController;
use App\Http\Controllers\Api\V1\Engagement\SurveyController;
use App\Http\Controllers\Api\V1\Enrollment\CourseProgressController;
use App\Http\Controllers\Api\V1\Enrollment\EnrollmentCodeRedeemController;
use App\Http\Controllers\Api\V1\Enrollment\EnrollmentController;
use App\Http\Controllers\Api\V1\Enrollment\LessonCheckpointController;
use App\Http\Controllers\Api\V1\Enrollment\LessonContentController;
use App\Http\Controllers\Api\V1\Enrollment\LessonProgressController;
use App\Http\Controllers\Api\V1\Enrollment\MediaStreamController;
use App\Http\Controllers\Api\V1\Enrollment\PlaybackController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Learning\LessonNoteController;
use App\Http\Controllers\Api\V1\Learning\PathController;
use App\Http\Controllers\Api\V1\Learning\PathEnrollmentController;
use App\Http\Controllers\Api\V1\Learning\StudyPlanController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Notification\PreferenceController;
use App\Http\Controllers\Api\V1\Platform\PublicStatsController;
use App\Http\Controllers\Api\V1\ProfileController;
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

    // Re-send the verification mail (generic response, enumeration-safe).
    Route::post('email/resend', ResendVerificationController::class)
        ->middleware('throttle:6,1')->name('verification.resend');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', LogoutController::class)->name('logout');
        Route::get('me', MeController::class)->name('me');
    });
});

// Signed verification link target. The global name `verification.verify` is
// required by Illuminate's VerifyEmail notification. The `signed` middleware
// authenticates the request, so no Sanctum token is needed here.
Route::get('auth/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Self-service account management + PDPL data-subject rights.
Route::middleware('auth:sanctum')->prefix('account')->name('api.account.')->group(function () {
    Route::patch('/', [AccountController::class, 'update'])->name('update');
    Route::put('password', [AccountController::class, 'password'])->name('password');
    Route::get('export', [AccountController::class, 'export'])->name('export');
    Route::delete('/', [AccountController::class, 'destroy'])->name('destroy');
});

Route::middleware('auth:sanctum')->prefix('admin')->name('api.admin.')->group(function () {
    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::patch('settings/payments', [SettingsController::class, 'updatePayments'])
        ->name('settings.payments.update');

    // AI assistant engine selection (off / rules / claude).
    Route::patch('settings/assistant', [SettingsController::class, 'updateAssistant'])->name('settings.assistant.update');

    // Admin analyst «بصيرة» (analytics access + assistant enabled).
    Route::post('assistant/chat', [AnalystController::class, 'chat'])
        ->middleware('assistant.enabled')->name('assistant.chat');

    // Contact-form triage (community.moderate permission).
    Route::get('contact-messages', [ContactController::class, 'index'])->name('contact.index');
    Route::post('contact-messages/{message}/handle', [ContactController::class, 'handle'])->name('contact.handle');

    // Editable site content — branding, copy, colours, images (content.manage).
    Route::get('site-content', [AdminSiteContentController::class, 'index'])->name('site-content.index');
    Route::patch('site-content', [AdminSiteContentController::class, 'update'])->name('site-content.update');
    Route::post('site-content/{key}/image', [AdminSiteContentController::class, 'uploadImage'])->name('site-content.image.upload');
    Route::delete('site-content/{key}/image', [AdminSiteContentController::class, 'clearImage'])->name('site-content.image.clear');

    // User management & impersonation (users.manage — Super Admin).
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');
    Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status.update');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.password.reset');
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::get('users/{user}/courses', [UserController::class, 'courses'])->name('users.courses');
    Route::post('users/{user}/retire', [UserController::class, 'retire'])->name('users.retire');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('courses/{course}/instructor', [UserController::class, 'transferCourse'])->name('courses.transfer');

    // Audit trail.
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

/*
|--------------------------------------------------------------------------
| Public site content — news, contact form, marketing stats
|--------------------------------------------------------------------------
| Powers the public home/news/about/contact pages. Aggregate stats only
| (PDPL); the contact form is rate-limited and strictly validated.
*/
Route::get('platform/stats', PublicStatsController::class)->name('api.platform.stats');

// Public editable site content (branding, copy, colours, image URLs).
Route::get('content/site', SiteContentController::class)->name('api.content.site');

/*
|--------------------------------------------------------------------------
| Engagement — reviews, gamification, profiles
|--------------------------------------------------------------------------
*/
Route::get('catalog/courses/{course}/reviews', [ReviewController::class, 'index'])->name('api.reviews.index');
Route::get('engagement/leaderboard', [GamificationController::class, 'leaderboard'])->name('api.engagement.leaderboard');
Route::get('profiles/learners/{user}', [ProfileController::class, 'learner'])->name('api.profiles.learner');
Route::get('profiles/instructors/{user}', [ProfileController::class, 'instructor'])->name('api.profiles.instructor');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('catalog/courses/{course}/reviews', [ReviewController::class, 'store'])->name('api.reviews.store');
    Route::get('engagement/me', [GamificationController::class, 'me'])->name('api.engagement.me');

    // Learner tutor «مُعين» — course-scoped, gated by assistant mode.
    Route::middleware('assistant.enabled')->group(function () {
        Route::post('assistant/courses/{course}/chat', [TutorController::class, 'chat'])->name('api.assistant.tutor.chat');
        Route::get('assistant/courses/{course}/history', [TutorController::class, 'history'])->name('api.assistant.tutor.history');
    });
});

Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.contact.store');

Route::prefix('content')->name('api.content.')->group(function () {
    Route::get('news', [NewsController::class, 'index'])->name('news.index');
    Route::get('news/{news}', [NewsController::class, 'show'])->name('news.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('news', [NewsController::class, 'store'])->name('news.store');
        Route::patch('news/{news}', [NewsController::class, 'update'])->name('news.update');
        Route::delete('news/{news}', [NewsController::class, 'destroy'])->name('news.destroy');
    });
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
        Route::get('mine', [CourseController::class, 'mine'])->name('courses.mine');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');

        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::patch('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        // Publishing workflow.
        Route::post('courses/{course}/submit', [CoursePublishingController::class, 'submit'])->name('courses.submit');
        Route::post('courses/{course}/approve', [CoursePublishingController::class, 'approve'])->name('courses.approve');
        Route::post('courses/{course}/reject', [CoursePublishingController::class, 'reject'])->name('courses.reject');

        // Sections (shallow-nested under courses).
        Route::post('courses/{course}/cover', [CourseController::class, 'uploadCover'])->name('courses.cover');
        Route::post('courses/{course}/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('courses/{course}/sections/order', [SectionController::class, 'reorder'])->name('sections.reorder');
        Route::patch('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

        // Lessons (shallow-nested under sections).
        Route::post('sections/{section}/lessons', [LessonController::class, 'store'])->name('lessons.store');
        Route::put('sections/{section}/lessons/order', [LessonController::class, 'reorder'])->name('lessons.reorder');
        Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
        Route::patch('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
        Route::post('lessons/{lesson}/asset', [LessonController::class, 'uploadAsset'])->name('lessons.asset');
        Route::post('lessons/{lesson}/transcript/auto', [LessonTranscriptController::class, 'auto'])->name('lessons.transcript.auto');
    });
});

/*
|--------------------------------------------------------------------------
| Learning — specialised paths & personal study plans
|--------------------------------------------------------------------------
| Paths bundle courses into ordered levels taken in sequence and end with
| a path certificate; study plans are the learner's own course lists with
| recurring reminders until completion.
*/
Route::prefix('learning')->name('api.learning.')->group(function () {
    Route::get('paths', [PathController::class, 'index'])->name('paths.index');
    Route::get('paths/{path}', [PathController::class, 'show'])->name('paths.show');

    Route::middleware('auth:sanctum')->group(function () {
        // Staff authoring (paths.manage).
        Route::post('paths', [PathController::class, 'store'])->name('paths.store');
        Route::patch('paths/{path}', [PathController::class, 'update'])->name('paths.update');
        Route::delete('paths/{path}', [PathController::class, 'destroy'])->name('paths.destroy');
        Route::post('paths/{path}/cover', [PathController::class, 'uploadCover'])->name('paths.cover');
        Route::put('paths/{path}/items', [PathController::class, 'syncItems'])->name('paths.items.sync');

        // Learner membership & sequential course enrollment.
        Route::get('my/paths', [PathEnrollmentController::class, 'mine'])->name('paths.mine');
        Route::post('paths/{path}/enroll', [PathEnrollmentController::class, 'enroll'])->name('paths.enroll');
        Route::post('paths/{path}/courses/{course}/enroll', [PathEnrollmentController::class, 'enrollCourse'])
            ->name('paths.courses.enroll');

        // Personal study plans (owner-only).
        Route::get('study-plans', [StudyPlanController::class, 'index'])->name('plans.index');
        Route::post('study-plans', [StudyPlanController::class, 'store'])->name('plans.store');
        Route::patch('study-plans/{plan}', [StudyPlanController::class, 'update'])->name('plans.update');
        Route::delete('study-plans/{plan}', [StudyPlanController::class, 'destroy'])->name('plans.destroy');
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
    Route::get('lessons/{lesson}/content', [LessonContentController::class, 'show'])->name('lesson.content');
    // Video interaction: time-anchored private notes + in-video checkpoints.
    Route::get('lessons/{lesson}/notes', [LessonNoteController::class, 'index'])->name('lesson.notes.index');
    Route::post('lessons/{lesson}/notes', [LessonNoteController::class, 'store'])->name('lesson.notes.store');
    Route::delete('lesson-notes/{note}', [LessonNoteController::class, 'destroy'])->name('lesson.notes.destroy');
    Route::get('lessons/{lesson}/checkpoints', [LessonCheckpointController::class, 'index'])->name('lesson.checkpoints');
    Route::post('lessons/{lesson}/checkpoints/{question}/answer', [LessonCheckpointController::class, 'answer'])->name('lesson.checkpoints.answer');
    Route::get('catalog/courses/{course}/progress', [CourseProgressController::class, 'show'])->name('course.progress');
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

    // The learner's gradebook for a course (overall grade + per-assessment).
    Route::get('courses/{course}/grade', CourseGradeController::class)->name('courses.grade');
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

/*
|--------------------------------------------------------------------------
| Admin tools — bulk CSV import, enrollment codes, cloning, CSV reports
|--------------------------------------------------------------------------
| Staff utilities: onboard a cohort from a CSV, issue/revoke self-enrollment
| codes, deep-copy a course for a new term, and export CSV reports. The
| learner-facing redeem endpoint only requires authentication.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->name('api.admin.tools.')->group(function () {
        Route::post('users/import', [UserImportController::class, 'store'])
            ->name('users.import');

        Route::get('courses/{course}/enrollment-codes', [EnrollmentCodeController::class, 'index'])
            ->name('codes.index');
        Route::post('courses/{course}/enrollment-codes', [EnrollmentCodeController::class, 'store'])
            ->name('codes.store');
        Route::delete('enrollment-codes/{code}', [EnrollmentCodeController::class, 'destroy'])
            ->name('codes.destroy');

        Route::post('courses/{course}/clone', CourseCloneController::class)
            ->name('courses.clone');

        Route::get('reports/enrollments.csv', [ReportController::class, 'enrollments'])
            ->name('reports.enrollments');
        Route::get('reports/courses.csv', [ReportController::class, 'courses'])
            ->name('reports.courses');
    });

    Route::post('enrollment-codes/redeem', EnrollmentCodeRedeemController::class)
        ->name('api.enrollment-codes.redeem');
});

/*
|--------------------------------------------------------------------------
| NELC compliance — satisfaction surveys & licence settings
|--------------------------------------------------------------------------
| (maintained by: nelc-compliance worktree)
| Learner satisfaction surveys (completers only), the admin quality
| summary (analytics.view, anonymous comments — PDPL), and the NELC
| licence number setting (settings.manage).
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('engagement/courses/{course}/survey', [SurveyController::class, 'store'])
        ->name('api.engagement.surveys.store');

    Route::get('admin/surveys/summary', SurveySummaryController::class)
        ->name('api.admin.surveys.summary');

    Route::patch('admin/settings/nelc', [SettingsController::class, 'updateNelc'])
        ->name('api.admin.settings.nelc.update');
});
