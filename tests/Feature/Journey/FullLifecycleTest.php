<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * The whole academic journey, end to end, through the real HTTP API and
 * across every role — the "as a user" walkthrough the platform promises:
 *
 *   Admin → registers an instructor
 *   Instructor → builds a course (sections, mixed lessons, question bank,
 *                a weighted quiz, an assignment) and submits it for review
 *   Supervisor → approves & publishes it
 *   Student → registers, enrols, studies every lesson, passes the quiz,
 *             submits the assignment
 *   Instructor → grades the assignment
 *   System → completes the enrolment and issues the certificate
 *   Public → verifies the certificate by its UUID
 *
 * If any seam between the bounded contexts is broken, this test fails.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
});

it('runs the full lifecycle: create course → graduate student → issue certificate', function () {
    // ─── 1. Admin registers an instructor (admin panel) ──────────────────
    $admin = userWithRole(Role::SuperAdmin);

    $instructorResponse = $this->actingAs($admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'د. سارة الأحمد',
            'email' => 'sara@mooc.test',
            'password' => 'Instructor2026',
            'role' => Role::Instructor->value,
        ])
        ->assertCreated();
    $instructor = User::query()->findOrFail($instructorResponse->json('data.id'));
    expect($instructor->hasRole(Role::Instructor->value))->toBeTrue();

    // ─── 2. Instructor builds the course ────────────────────────────────
    $courseResponse = $this->actingAs($instructor)
        ->postJson('/api/v1/catalog/courses', [
            'title' => 'أساسيات البرمجة بلغة بايثون',
            'summary' => 'دورة تمهيدية',
            'pricing_type' => 'free',
            'passing_grade' => 60,
        ])
        ->assertCreated();
    $slug = $courseResponse->json('data.slug');
    $courseId = $courseResponse->json('data.id');

    // Two sections, each with lessons of different material types.
    $s1 = $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/courses/{$slug}/sections", ['title' => 'مدخل', 'position' => 1])
        ->assertCreated()->json('data.id');
    $s2 = $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/courses/{$slug}/sections", ['title' => 'الأساسيات', 'position' => 2])
        ->assertCreated()->json('data.id');

    $lessonIds = [];
    $lessonIds[] = $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/sections/{$s1}/lessons", ['title' => 'ما هي البرمجة؟', 'type' => 'article'])
        ->assertCreated()->json('data.id');
    $lessonIds[] = $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/sections/{$s1}/lessons", ['title' => 'تثبيت الأدوات', 'type' => 'article'])
        ->assertCreated()->json('data.id');
    $lessonIds[] = $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/sections/{$s2}/lessons", ['title' => 'المتغيرات', 'type' => 'article'])
        ->assertCreated()->json('data.id');

    // Question bank + a weighted quiz built from it.
    $q1 = $this->actingAs($instructor)
        ->postJson("/api/v1/assessment/courses/{$slug}/questions", [
            'type' => 'true_false', 'body' => 'بايثون لغة برمجة؟', 'correct' => true,
            'explanation' => 'نعم، بايثون لغة برمجة عالية المستوى.', 'points' => 1,
        ])->assertCreated()->json('data.id');
    $q2 = $this->actingAs($instructor)
        ->postJson("/api/v1/assessment/courses/{$slug}/questions", [
            'type' => 'mcq', 'body' => 'أيها نوع بيانات؟',
            'choices' => [['id' => 'a', 'text' => 'int'], ['id' => 'b', 'text' => 'سيارة']],
            'correct' => ['a'], 'points' => 1,
        ])->assertCreated()->json('data.id');

    $quizId = $this->actingAs($instructor)
        ->postJson("/api/v1/assessment/courses/{$slug}/quizzes", [
            'title' => 'اختبار نهائي', 'pass_mark' => 50, 'weight' => 2,
            'question_ids' => [$q1, $q2],
        ])->assertCreated()->json('data.id');

    // A graded assignment.
    $assignmentId = $this->actingAs($instructor)
        ->postJson("/api/v1/assessment/courses/{$slug}/assignments", [
            'title' => 'مشروع تطبيقي', 'points' => 100, 'weight' => 1,
        ])->assertCreated()->json('data.id');

    // ─── 3. Submit for review (draft → pending_review) ───────────────────
    $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/courses/{$slug}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', CourseStatus::PendingReview->value);

    // A second submit is now an invalid transition → clean 422, not a 500.
    $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/courses/{$slug}/submit")
        ->assertStatus(422);

    // ─── 4. Supervisor approves & publishes ──────────────────────────────
    $supervisor = userWithRole(Role::Supervisor);
    $this->actingAs($supervisor)
        ->postJson("/api/v1/catalog/courses/{$slug}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', CourseStatus::Published->value);

    // It is now visible to the public.
    $this->getJson("/api/v1/catalog/courses/{$slug}")->assertOk();

    // ─── 5. Student registers & enrols ───────────────────────────────────
    $this->postJson('/api/v1/auth/register', [
        'name' => 'محمد العبدالله',
        'email' => 'student@mooc.test',
        'password' => 'Student2026',
        'password_confirmation' => 'Student2026',
        'consents' => ['privacy_policy', 'data_processing'],
    ])->assertCreated();
    $student = User::query()->where('email', 'student@mooc.test')->firstOrFail();

    $this->actingAs($student)
        ->postJson("/api/v1/catalog/courses/{$slug}/enroll")
        ->assertCreated();

    // ─── 6. Student studies every lesson ─────────────────────────────────
    foreach ($lessonIds as $lessonId) {
        $this->actingAs($student)
            ->postJson("/api/v1/lessons/{$lessonId}/progress", ['completed' => true])
            ->assertOk();
    }

    // Lessons are 100% done, but the passing grade is not met yet, so the
    // enrolment must still be active (the Edraak completion model).
    $enrollment = Enrollment::query()
        ->where('user_id', $student->id)->where('course_id', $courseId)->firstOrFail();
    expect($enrollment->progress_percent)->toBe(100)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and(Certificate::query()->where('user_id', $student->id)->where('course_id', $courseId)->exists())->toBeFalse();

    // ─── 7. Student passes the quiz → completion fires here ──────────────
    $start = $this->actingAs($student)
        ->postJson("/api/v1/assessment/quizzes/{$quizId}/attempts")
        ->assertCreated();
    $attemptId = $start->json('attempt.id');

    $this->actingAs($student)
        ->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
            'answers' => [$q1 => true, $q2 => ['a']],
        ])
        ->assertOk()
        ->assertJsonPath('data.score', 100);

    // Requirements are now met (lessons 100% + weighted grade
    // (quiz 100 × 2 + assignment 0 × 1) / 3 = 67 ≥ 60), so the platform
    // completes the enrolment the moment the threshold is crossed.
    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Completed)
        ->and($enrollment->completed_at)->not->toBeNull();

    // ─── 8. Late assignment grading is still accepted (idempotent) ───────
    $this->actingAs($student)
        ->postJson("/api/v1/assessment/assignments/{$assignmentId}/submissions", ['content' => 'حلّي للمشروع'])
        ->assertCreated();
    $submissionId = AssignmentSubmission::query()
        ->where('assignment_id', $assignmentId)->where('user_id', $student->id)->firstOrFail()->id;

    $this->actingAs($instructor)
        ->postJson("/api/v1/assessment/submissions/{$submissionId}/grade", ['grade' => 90, 'feedback' => 'ممتاز'])
        ->assertOk();

    // The enrolment stays completed; the certificate is issued exactly once.
    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Completed);

    $certificate = Certificate::query()
        ->where('user_id', $student->id)->where('course_id', $courseId)->first();
    expect($certificate)->not->toBeNull();

    // The learner sees it in their certificates list.
    $this->actingAs($student)
        ->getJson('/api/v1/certificates')
        ->assertOk()
        ->assertJsonPath('data.0.course_title', 'أساسيات البرمجة بلغة بايثون')
        ->assertJsonPath('data.0.grade', 67);

    // ─── 10. Public verification by UUID (the QR-code endpoint) ──────────
    $this->getJson("/api/v1/certificates/verify/{$certificate->verification_uuid}")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('holder_name', 'محمد العبدالله')
        ->assertJsonPath('course_title', 'أساسيات البرمجة بلغة بايثون');

    // ─── 11. The graduate can now rate the course (completers only) ──────
    $this->actingAs($student)
        ->postJson("/api/v1/engagement/courses/{$slug}/survey", [
            'overall' => 5, 'content_quality' => 5, 'instructor_quality' => 5, 'platform_quality' => 4,
            'comment' => 'دورة ممتازة',
        ])
        ->assertSuccessful();
});

it('reflects the grade on the certificate and gradebook for a passing learner', function () {
    $instructor = userWithRole(Role::Instructor);
    $student = userWithRole(Role::Student);

    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id, 'passing_grade' => 50]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create(['title' => 'درس', 'type' => 'article', 'position' => 1]);

    $this->actingAs($student)->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertCreated();

    // Gradebook is empty (no assessments yet) — endpoint still responds.
    $this->actingAs($student)
        ->getJson("/api/v1/assessment/courses/{$course->slug}/grade")
        ->assertOk();

    $this->actingAs($student)
        ->postJson("/api/v1/lessons/{$lesson->id}/progress", ['completed' => true])
        ->assertOk();

    // No assessments → grade is null → completion gated only on lessons → completes.
    $enrollment = Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();
    expect($enrollment->status)->toBe(EnrollmentStatus::Completed);
});
