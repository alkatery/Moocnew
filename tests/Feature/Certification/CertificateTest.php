<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Application\CertificateService;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Application\ProgressService;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => Storage::fake('local'));

/**
 * Build a published course with one lesson and an enrolled learner.
 */
function completableCourse(): array
{
    $course = Course::factory()->published()->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create(['title' => 'درس', 'type' => 'article', 'position' => 1]);

    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $course);

    return [$course, $lesson, $learner];
}

it('issues a certificate automatically when a course is completed', function () {
    [$course, $lesson, $learner] = completableCourse();
    $enrollment = Enrollment::query()
        ->where('user_id', $learner->id)
        ->where('course_id', $course->id)
        ->firstOrFail();

    // Completing the only lesson finishes the course → certificate issued.
    app(ProgressService::class)->record($enrollment, $lesson, completed: true);

    $certificate = Certificate::query()->where('user_id', $learner->id)->where('course_id', $course->id)->first();
    expect($certificate)->not->toBeNull();
    expect($certificate->serial)->toStartWith('MOOC-');
    expect($certificate->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($certificate->pdf_path);
});

it('generates a real PDF document', function () {
    [$course] = completableCourse();
    $learner = User::factory()->create();

    $certificate = app(CertificateService::class)->issueFor($learner->id, $course->id);

    $bytes = Storage::disk('local')->get($certificate->pdf_path);
    expect(substr($bytes, 0, 4))->toBe('%PDF');
});

it('is idempotent — issuing twice returns the same certificate', function () {
    [$course] = completableCourse();
    $learner = User::factory()->create();
    $service = app(CertificateService::class);

    $first = $service->issueFor($learner->id, $course->id);
    $second = $service->issueFor($learner->id, $course->id);

    expect($second->id)->toBe($first->id);
    expect(Certificate::query()->count())->toBe(1);
});

it('verifies a genuine certificate publicly without authentication', function () {
    [$course] = completableCourse();
    $learner = User::factory()->create(['name' => 'طالب مجتهد']);
    $certificate = app(CertificateService::class)->issueFor($learner->id, $course->id);

    $this->getJson("/api/v1/certificates/verify/{$certificate->verification_uuid}")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('holder_name', 'طالب مجتهد')
        ->assertJsonPath('serial', $certificate->serial);
});

it('reports an unknown certificate as invalid', function () {
    $this->getJson('/api/v1/certificates/verify/'.Str::uuid())
        ->assertStatus(404)
        ->assertJsonPath('valid', false);
});

it('lets the holder download their certificate but not others', function () {
    [$course] = completableCourse();
    $holder = User::factory()->create();
    $certificate = app(CertificateService::class)->issueFor($holder->id, $course->id);

    Sanctum::actingAs(User::factory()->create()); // not the holder
    $this->get("/api/v1/certificates/{$certificate->verification_uuid}/download")->assertForbidden();

    Sanctum::actingAs($holder);
    $this->get("/api/v1/certificates/{$certificate->verification_uuid}/download")->assertOk();
});
