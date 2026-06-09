<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->instructor = userWithRole(Role::Instructor);
    $this->course = Course::factory()->published()->for($this->instructor, 'instructor')->create();
});

it('lets the instructor create an assignment', function () {
    Sanctum::actingAs($this->instructor);

    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/assignments", [
        'title' => 'الواجب الأول',
        'points' => 50,
    ])->assertCreated()->assertJsonPath('data.points', 50);
});

it('lets an enrolled learner submit work and the instructor grade it', function () {
    Storage::fake('media');
    $assignment = Assignment::query()->create(['course_id' => $this->course->id, 'title' => 'واجب', 'points' => 100]);

    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $this->course);

    Sanctum::actingAs($learner);
    $submissionId = $this->postJson("/api/v1/assessment/assignments/{$assignment->id}/submissions", [
        'content' => 'هذه إجابتي.',
        'file' => UploadedFile::fake()->create('answer.pdf', 100, 'application/pdf'),
    ])->assertCreated()->assertJsonPath('data.has_file', true)->json('data.id');

    // Instructor grades it.
    Sanctum::actingAs($this->instructor);
    $this->postJson("/api/v1/assessment/submissions/{$submissionId}/grade", [
        'grade' => 85,
        'feedback' => 'عمل جيد',
    ])->assertOk()->assertJsonPath('data.grade', 85);

    $this->assertDatabaseHas('activity_logs', ['event' => 'assignment.graded']);
});

it('forbids a non-enrolled user from submitting', function () {
    $assignment = Assignment::query()->create(['course_id' => $this->course->id, 'title' => 'واجب']);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/assessment/assignments/{$assignment->id}/submissions", [
        'content' => 'محاولة',
    ])->assertForbidden();
});

it('forbids a learner from grading submissions', function () {
    $assignment = Assignment::query()->create(['course_id' => $this->course->id, 'title' => 'واجب']);
    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $this->course);
    Sanctum::actingAs($learner);

    $submissionId = $this->postJson("/api/v1/assessment/assignments/{$assignment->id}/submissions", [
        'content' => 'إجابة',
    ])->json('data.id');

    $this->postJson("/api/v1/assessment/submissions/{$submissionId}/grade", ['grade' => 100])
        ->assertForbidden();
});

it('requires content or a file', function () {
    $assignment = Assignment::query()->create(['course_id' => $this->course->id, 'title' => 'واجب']);
    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $this->course);
    Sanctum::actingAs($learner);

    $this->postJson("/api/v1/assessment/assignments/{$assignment->id}/submissions", [])
        ->assertStatus(422);
});
