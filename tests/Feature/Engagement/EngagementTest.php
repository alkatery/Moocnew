<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Engagement\Application\GamificationService;
use App\Contexts\Engagement\Domain\PointRule;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function reviewableCourse(): array
{
    $course = Course::factory()->published()->create(['pricing_type' => 'free', 'price_minor' => 0]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create(['title' => 'درس', 'type' => 'article', 'content' => 'م', 'position' => 1]);

    return [$course, $lesson];
}

it('lets an enrolled learner review a course and exposes the summary', function () {
    [$course] = reviewableCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/reviews", ['rating' => 5, 'comment' => 'ممتازة'])
        ->assertCreated();

    $this->getJson("/api/v1/catalog/courses/{$course->slug}/reviews")
        ->assertOk()
        ->assertJsonPath('summary.average', 5)
        ->assertJsonPath('summary.count', 1)
        ->assertJsonPath('data.0.comment', 'ممتازة');

    // A second submission updates the same review (one per learner).
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/reviews", ['rating' => 3])->assertCreated();
    $this->getJson("/api/v1/catalog/courses/{$course->slug}/reviews")->assertJsonPath('summary.count', 1);
});

it('forbids reviewing a course the learner is not enrolled in', function () {
    [$course] = reviewableCourse();
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/reviews", ['rating' => 5])->assertForbidden();
});

it('surfaces the average rating on the catalogue listing', function () {
    [$course] = reviewableCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/reviews", ['rating' => 4]);

    $this->getJson('/api/v1/catalog/courses')
        ->assertOk()
        ->assertJsonPath('data.0.rating', 4)
        ->assertJsonPath('data.0.reviews_count', 1);
});

it('awards points and a streak for completing lessons and courses', function () {
    [$course, $lesson] = reviewableCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $this->postJson("/api/v1/lessons/{$lesson->id}/progress", ['completed' => true])->assertOk();

    // lesson (10) + course completion (100, single lesson finishes it) = 110.
    $this->getJson('/api/v1/engagement/me')
        ->assertOk()
        ->assertJsonPath('data.points', 110)
        ->assertJsonPath('data.current_streak', 1)
        ->assertJsonPath('data.badges', ['first_course']);
});

it('makes point awards idempotent per source', function () {
    $student = userWithRole(Role::Student);
    $service = app(GamificationService::class);

    $service->award($student->id, PointRule::LessonCompleted, 'lesson:1');
    $service->award($student->id, PointRule::LessonCompleted, 'lesson:1'); // duplicate

    expect($student->fresh() && true)->toBeTrue();
    $this->assertDatabaseCount('point_awards', 1);
});

it('ranks learners on the public leaderboard', function () {
    $a = userWithRole(Role::Student);
    $b = userWithRole(Role::Student);
    app(GamificationService::class)->award($a->id, PointRule::CourseCompleted, 'course:1'); // 100
    app(GamificationService::class)->award($b->id, PointRule::LessonCompleted, 'lesson:1'); // 10

    $this->getJson('/api/v1/engagement/leaderboard')
        ->assertOk()
        ->assertJsonPath('data.0.name', $a->name)
        ->assertJsonPath('data.0.points', 100)
        ->assertJsonPath('data.1.name', $b->name);
});

it('serves a public learner profile with achievements', function () {
    [$course, $lesson] = reviewableCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);
    $this->postJson("/api/v1/lessons/{$lesson->id}/progress", ['completed' => true])->assertOk();

    $this->getJson("/api/v1/profiles/learners/{$student->id}")
        ->assertOk()
        ->assertJsonPath('data.name', $student->name)
        ->assertJsonPath('data.points', 110)
        ->assertJsonPath('data.certificates.0.type', 'course');
});

it('serves a public instructor profile and 404s for non-instructors', function () {
    $instructor = userWithRole(Role::Instructor);
    Course::factory()->for($instructor, 'instructor')->published()->create();

    $this->getJson("/api/v1/profiles/instructors/{$instructor->id}")
        ->assertOk()
        ->assertJsonPath('data.name', $instructor->name)
        ->assertJsonPath('data.stats.courses', 1);

    $student = userWithRole(Role::Student);
    $this->getJson("/api/v1/profiles/instructors/{$student->id}")->assertNotFound();
});

it('uploads a course cover image (owner only)', function () {
    Storage::fake('public');
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();

    Sanctum::actingAs(userWithRole(Role::Student));
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/cover", [])->assertForbidden();

    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/cover", [
        'image' => UploadedFile::fake()->image('cover.jpg', 800, 450),
    ])->assertOk()->assertJsonPath('data.cover_image', fn ($v) => str_contains((string) $v, '/storage/covers/'));

    expect($course->fresh()->cover_image)->toContain('/storage/covers/');
});
