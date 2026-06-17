<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Application\CertificateService;
use App\Contexts\Engagement\Infrastructure\Persistence\CourseSurvey;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Contexts\Platform\Infrastructure\Persistence\SiteContent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A published course plus a learner enrolled with the given status.
 */
function nelcCourseWithLearner(EnrollmentStatus $status): array
{
    $course = Course::factory()->published()->create();
    $learner = userWithRole(Role::Student);

    Enrollment::query()->create([
        'user_id' => $learner->id,
        'course_id' => $course->id,
        'status' => $status,
        'progress_percent' => $status === EnrollmentStatus::Completed ? 100 : 10,
        'enrolled_at' => now(),
        'completed_at' => $status === EnrollmentStatus::Completed ? now() : null,
    ]);

    return [$course, $learner];
}

const NELC_SURVEY_ANSWERS = [
    'overall' => 5,
    'content_quality' => 4,
    'instructor_quality' => 5,
    'platform_quality' => 3,
    'comment' => 'تجربة ممتازة، شكراً.',
];

// ------------------------------------------------------ satisfaction survey

it('accepts the satisfaction survey from a learner who completed the course', function () {
    [$course, $learner] = nelcCourseWithLearner(EnrollmentStatus::Completed);

    Sanctum::actingAs($learner);
    $this->postJson("/api/v1/engagement/courses/{$course->slug}/survey", NELC_SURVEY_ANSWERS)
        ->assertCreated()
        ->assertJsonPath('data.overall', 5)
        ->assertJsonPath('data.platform_quality', 3);

    expect(CourseSurvey::query()->where('course_id', $course->id)->where('user_id', $learner->id)->exists())->toBeTrue();
});

it('rejects the survey from a learner who has not completed the course', function () {
    [$course, $learner] = nelcCourseWithLearner(EnrollmentStatus::Active);

    Sanctum::actingAs($learner);
    $this->postJson("/api/v1/engagement/courses/{$course->slug}/survey", NELC_SURVEY_ANSWERS)
        ->assertForbidden();

    // A complete outsider is rejected too.
    Sanctum::actingAs(userWithRole(Role::Student));
    $this->postJson("/api/v1/engagement/courses/{$course->slug}/survey", NELC_SURVEY_ANSWERS)
        ->assertForbidden();

    expect(CourseSurvey::query()->count())->toBe(0);
});

it('updates the existing survey instead of duplicating on resubmission', function () {
    [$course, $learner] = nelcCourseWithLearner(EnrollmentStatus::Completed);

    Sanctum::actingAs($learner);
    $this->postJson("/api/v1/engagement/courses/{$course->slug}/survey", NELC_SURVEY_ANSWERS)->assertCreated();
    $this->postJson("/api/v1/engagement/courses/{$course->slug}/survey", [
        ...NELC_SURVEY_ANSWERS,
        'overall' => 2,
        'comment' => 'غيّرت رأيي.',
    ])->assertOk();

    $surveys = CourseSurvey::query()->where('course_id', $course->id)->where('user_id', $learner->id)->get();
    expect($surveys)->toHaveCount(1)
        ->and($surveys->first()->overall)->toBe(2)
        ->and($surveys->first()->comment)->toBe('غيّرت رأيي.');
});

it('computes axis averages and anonymous comments in the admin summary', function () {
    [$course, $first] = nelcCourseWithLearner(EnrollmentStatus::Completed);
    $second = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $second->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Completed, 'progress_percent' => 100,
        'enrolled_at' => now(), 'completed_at' => now(),
    ]);

    Sanctum::actingAs($first);
    $this->postJson("/api/v1/engagement/courses/{$course->slug}/survey", [
        'overall' => 5, 'content_quality' => 4, 'instructor_quality' => 5, 'platform_quality' => 3,
        'comment' => 'رائعة',
    ])->assertCreated();

    Sanctum::actingAs($second);
    $this->postJson("/api/v1/engagement/courses/{$course->slug}/survey", [
        'overall' => 3, 'content_quality' => 2, 'instructor_quality' => 3, 'platform_quality' => 5,
    ])->assertCreated();

    Sanctum::actingAs(userWithRole(Role::SuperAdmin));
    $summary = $this->getJson("/api/v1/admin/surveys/summary?course_id={$course->id}")
        ->assertOk()
        ->json('data');

    expect($summary['count'])->toBe(2)
        ->and($summary['averages']['overall'])->toEqual(4.0)
        ->and($summary['averages']['content_quality'])->toEqual(3.0)
        ->and($summary['averages']['instructor_quality'])->toEqual(4.0)
        ->and($summary['averages']['platform_quality'])->toEqual(4.0)
        ->and($summary['comments'])->toHaveCount(1)
        ->and($summary['comments'][0]['comment'])->toBe('رائعة')
        // PDPL: comments carry no author identity.
        ->and($summary['comments'][0])->not->toHaveKeys(['user', 'user_id', 'name']);
});

it('blocks the survey summary for users without analytics.view', function () {
    Sanctum::actingAs(userWithRole(Role::Student));
    $this->getJson('/api/v1/admin/surveys/summary')->assertForbidden();
});

// --------------------------------------------------------- 35-seat live cap

it('rejects scheduling a live session for more than 35 participants', function () {
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->published()->for($instructor, 'instructor')->create();

    Sanctum::actingAs($instructor);
    $this->postJson("/api/v1/scheduling/courses/{$course->slug}/sessions", [
        'title' => 'جلسة كبيرة',
        'provider' => 'manual',
        'join_url' => 'https://meet.example/x',
        'starts_at' => now()->addDay()->toIso8601String(),
        'capacity' => 36,
    ])->assertStatus(422)
        ->assertJsonPath('errors.capacity.0', 'الحد الأقصى 35 متعلماً للجلسة المتزامنة وفق معايير المركز الوطني للتعليم الإلكتروني');

    $this->postJson("/api/v1/scheduling/courses/{$course->slug}/sessions", [
        'title' => 'جلسة كبيرة',
        'provider' => 'manual',
        'join_url' => 'https://meet.example/x',
        'starts_at' => now()->addDay()->toIso8601String(),
        'max_participants' => 50,
    ])->assertStatus(422);
});

it('defaults the NELC cap to 35 and exposes it on the session resource', function () {
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->published()->for($instructor, 'instructor')->create();

    Sanctum::actingAs($instructor);
    $this->postJson("/api/v1/scheduling/courses/{$course->slug}/sessions", [
        'title' => 'جلسة نظامية',
        'provider' => 'manual',
        'join_url' => 'https://meet.example/y',
        'starts_at' => now()->addDay()->toIso8601String(),
        'capacity' => 20,
    ])->assertCreated()
        ->assertJsonPath('data.max_participants', 35)
        ->assertJsonPath('data.capacity', 20)
        ->assertJsonPath('data.seats_remaining', 20);
});

// ------------------------------------------------------------ NELC licence

it('stores the licence number and surfaces it on public certificate verification', function () {
    Storage::fake('local');

    Sanctum::actingAs(userWithRole(Role::SuperAdmin));
    $this->patchJson('/api/v1/admin/settings/nelc', ['license_number' => 'NELC-L-2026-001'])
        ->assertOk()
        ->assertJsonPath('nelc_license_number', 'NELC-L-2026-001');

    $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertJsonPath('nelc_license_number', 'NELC-L-2026-001');

    [$course, $learner] = nelcCourseWithLearner(EnrollmentStatus::Completed);
    $certificate = app(CertificateService::class)->issueFor($learner->id, $course->id);

    $this->getJson("/api/v1/certificates/verify/{$certificate->verification_uuid}")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('nelc_license', 'NELC-L-2026-001');
});

it('returns a null licence on verification when none is configured', function () {
    Storage::fake('local');

    [$course, $learner] = nelcCourseWithLearner(EnrollmentStatus::Completed);
    $certificate = app(CertificateService::class)->issueFor($learner->id, $course->id);

    $this->getJson("/api/v1/certificates/verify/{$certificate->verification_uuid}")
        ->assertOk()
        ->assertJsonPath('nelc_license', null);
});

it('forbids setting the licence number without settings.manage', function () {
    Sanctum::actingAs(userWithRole(Role::Student));
    $this->patchJson('/api/v1/admin/settings/nelc', ['license_number' => 'X'])->assertForbidden();

    expect(app(SettingsRepository::class)->get(SettingKey::NelcLicenseNumber))->toBeNull();
});

// -------------------------------------------------------- published policies

it('seeds the attendance and integrity policy content', function () {
    $this->seed(SiteContentSeeder::class);

    foreach ([
        'policy.attendance.title',
        'policy.attendance.body',
        'policy.integrity.title',
        'policy.integrity.body',
    ] as $key) {
        $row = SiteContent::query()->where('key', $key)->first();
        expect($row)->not->toBeNull()
            ->and($row->group)->toBe('policies')
            ->and($row->value)->not->toBeEmpty();
    }

    // And the public content endpoint exposes them.
    $map = $this->getJson('/api/v1/content/site')->assertOk()->json('data');
    expect($map['policy.attendance.title'])->toBe('سياسة الحضور والمواظبة')
        ->and($map['policy.integrity.title'])->toBe('سياسة النزاهة الأكاديمية');
});
