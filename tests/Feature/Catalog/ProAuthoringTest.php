<?php

declare(strict_types=1);

use App\Contexts\Assessment\Application\CourseGradeService;
use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function authoringCourse(): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);
    $section = $course->sections()->create(['title' => 'القسم الأول', 'position' => 1]);

    return [$instructor, $course, $section];
}

// ---------------------------------------------------------------- reorder

it('reorders sections within a course', function () {
    [$instructor, $course, $s1] = authoringCourse();
    $s2 = $course->sections()->create(['title' => 'القسم الثاني', 'position' => 2]);

    $this->actingAs($instructor)
        ->putJson("/api/v1/catalog/courses/{$course->slug}/sections/order", ['ids' => [$s2->id, $s1->id]])
        ->assertOk();

    expect($s2->fresh()->position)->toBe(1)
        ->and($s1->fresh()->position)->toBe(2);
});

it('reorders lessons within a section and rejects foreign lesson ids', function () {
    [$instructor, , $section] = authoringCourse();
    $l1 = $section->lessons()->create(['title' => 'أ', 'type' => 'article', 'position' => 1]);
    $l2 = $section->lessons()->create(['title' => 'ب', 'type' => 'article', 'position' => 2]);

    $this->actingAs($instructor)
        ->putJson("/api/v1/catalog/sections/{$section->id}/lessons/order", ['ids' => [$l2->id, $l1->id]])
        ->assertOk();

    expect($l2->fresh()->position)->toBe(1);

    // An id list that doesn't exactly match the section's lessons is rejected.
    $this->actingAs($instructor)
        ->putJson("/api/v1/catalog/sections/{$section->id}/lessons/order", ['ids' => [$l1->id, 999999]])
        ->assertStatus(422);
});

it('forbids reordering by a non-owner', function () {
    [, $course] = authoringCourse();
    $stranger = userWithRole(Role::Instructor);

    $this->actingAs($stranger)
        ->putJson("/api/v1/catalog/courses/{$course->slug}/sections/order", ['ids' => [1]])
        ->assertForbidden();
});

// ------------------------------------------------------------ lesson asset

it('uploads a pdf asset to a file lesson and an image to an image lesson', function () {
    Storage::fake('public');
    [$instructor, , $section] = authoringCourse();
    $file = $section->lessons()->create(['title' => 'ملف', 'type' => 'file', 'position' => 1]);
    $image = $section->lessons()->create(['title' => 'صورة', 'type' => 'image', 'position' => 2]);

    $this->actingAs($instructor)
        ->post("/api/v1/catalog/lessons/{$file->id}/asset", ['file' => UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf')])
        ->assertOk()
        ->assertJsonPath('data.asset_path', fn ($url) => str_contains((string) $url, '/storage/lesson-assets/'));

    $this->actingAs($instructor)
        ->post("/api/v1/catalog/lessons/{$image->id}/asset", ['file' => UploadedFile::fake()->image('shot.png')])
        ->assertOk();

    expect($file->fresh()->asset_path)->not->toBeNull()
        ->and($image->fresh()->asset_path)->not->toBeNull();
});

it('rejects disallowed asset types', function () {
    Storage::fake('public');
    [$instructor, , $section] = authoringCourse();
    $lesson = $section->lessons()->create(['title' => 'ملف', 'type' => 'file', 'position' => 1]);

    $this->actingAs($instructor)
        ->post("/api/v1/catalog/lessons/{$lesson->id}/asset", [
            'file' => UploadedFile::fake()->create('app.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

// ------------------------------------------------------- lesson authoring

it('shows full authoring data to the owner and updates transcript', function () {
    [$instructor, , $section] = authoringCourse();
    $lesson = $section->lessons()->create([
        'title' => 'درس', 'type' => 'article', 'content' => 'نص المقال', 'position' => 1,
    ]);

    $this->actingAs($instructor)
        ->getJson("/api/v1/catalog/lessons/{$lesson->id}")
        ->assertOk()
        ->assertJsonPath('data.content', 'نص المقال');

    $this->actingAs($instructor)
        ->patchJson("/api/v1/catalog/lessons/{$lesson->id}", ['transcript' => 'تفريغ تجريبي'])
        ->assertOk();

    expect($lesson->fresh()->transcript)->toBe('تفريغ تجريبي');
});

// ------------------------------------------------------- lesson content

it('delivers article content, image and file assets to enrolled learners', function () {
    [, $course, $section] = authoringCourse();
    $article = $section->lessons()->create(['title' => 'مقال', 'type' => 'article', 'content' => 'المحتوى', 'transcript' => null, 'position' => 1]);
    $image = $section->lessons()->create(['title' => 'صورة', 'type' => 'image', 'asset_path' => '/storage/lesson-assets/a.png', 'position' => 2]);

    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Active, 'progress_percent' => 0, 'enrolled_at' => now(),
    ]);

    $this->actingAs($student)
        ->getJson("/api/v1/lessons/{$article->id}/content")
        ->assertOk()
        ->assertJsonPath('data.content', 'المحتوى');

    $this->actingAs($student)
        ->getJson("/api/v1/lessons/{$image->id}/content")
        ->assertOk()
        ->assertJsonPath('data.asset_path', '/storage/lesson-assets/a.png');
});

it('blocks lesson content for non-enrolled users unless free preview', function () {
    [, , $section] = authoringCourse();
    $locked = $section->lessons()->create(['title' => 'مقفل', 'type' => 'article', 'content' => 'س', 'position' => 1]);
    $preview = $section->lessons()->create(['title' => 'معاينة', 'type' => 'article', 'content' => 'م', 'position' => 2, 'is_free_preview' => true]);

    $outsider = userWithRole(Role::Student);

    $this->actingAs($outsider)->getJson("/api/v1/lessons/{$locked->id}/content")->assertForbidden();
    $this->actingAs($outsider)->getJson("/api/v1/lessons/{$preview->id}/content")->assertOk();
});

// ------------------------------------------- staff assigns an instructor

it('lets staff create a course on behalf of an instructor', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $instructor = userWithRole(Role::Instructor);

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/catalog/courses', [
            'title' => 'دورة مسندة',
            'pricing_type' => 'free',
            'instructor_id' => $instructor->id,
        ])
        ->assertCreated();

    expect(Course::query()->find($response->json('data.id'))->instructor_id)->toBe($instructor->id);
});

it('rejects assigning a course to a non-instructor user', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $student = userWithRole(Role::Student);

    $this->actingAs($admin)
        ->postJson('/api/v1/catalog/courses', [
            'title' => 'دورة', 'pricing_type' => 'free', 'instructor_id' => $student->id,
        ])
        ->assertStatus(422);
});

it('ignores instructor_id from a regular instructor (owns what they create)', function () {
    $instructor = userWithRole(Role::Instructor);
    $other = userWithRole(Role::Instructor);

    $response = $this->actingAs($instructor)
        ->postJson('/api/v1/catalog/courses', [
            'title' => 'دورتي', 'pricing_type' => 'free', 'instructor_id' => $other->id,
        ])
        ->assertCreated();

    expect(Course::query()->find($response->json('data.id'))->instructor_id)->toBe($instructor->id);
});

it('lets staff reassign an existing course to another instructor', function () {
    [, $course] = authoringCourse();
    $admin = userWithRole(Role::SuperAdmin);
    $newOwner = userWithRole(Role::Instructor);

    $this->actingAs($admin)
        ->patchJson("/api/v1/catalog/courses/{$course->slug}", ['instructor_id' => $newOwner->id])
        ->assertOk();

    expect($course->fresh()->instructor_id)->toBe($newOwner->id);
});

// ----------------------------------------------------------- weighted grade

it('computes the weighted course grade from quiz and assignment weights', function () {
    [, $course] = authoringCourse();
    $student = userWithRole(Role::Student);

    $question = Question::query()->create([
        'course_id' => $course->id, 'type' => 'true_false', 'body' => 'سؤال', 'choices' => null, 'correct' => [true], 'points' => 1,
    ]);

    // Quiz (weight 3) best score 100; assignment (weight 1) graded 50%.
    $quiz = Quiz::query()->create(['course_id' => $course->id, 'title' => 'اختبار', 'pass_mark' => 60, 'weight' => 3]);
    $quiz->questions()->sync([$question->id => ['position' => 1]]);
    QuizAttempt::query()->create([
        'quiz_id' => $quiz->id, 'user_id' => $student->id, 'score' => 100,
        'started_at' => now()->subMinutes(5), 'submitted_at' => now(),
    ]);

    $assignment = Assignment::query()->create(['course_id' => $course->id, 'title' => 'واجب', 'points' => 100, 'weight' => 1]);
    AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id, 'user_id' => $student->id,
        'body' => 'حل', 'grade' => 50, 'submitted_at' => now(), 'graded_at' => now(),
    ]);

    $grade = app(CourseGradeService::class)
        ->gradeFor($student->id, $course->id);

    // (100×3 + 50×1) / 4 = 87.5 → 88
    expect($grade)->toBe(88);
});

it('stores weight and section on quizzes and assignments', function () {
    [$instructor, $course, $section] = authoringCourse();
    $question = Question::query()->create([
        'course_id' => $course->id, 'type' => 'true_false', 'body' => 'س', 'choices' => null, 'correct' => [true], 'points' => 1,
    ]);

    $this->actingAs($instructor)
        ->postJson("/api/v1/assessment/courses/{$course->slug}/quizzes", [
            'title' => 'اختبار القسم', 'question_ids' => [$question->id],
            'weight' => 4, 'section_id' => $section->id,
        ])
        ->assertCreated();

    $quiz = Quiz::query()->latest('id')->first();
    expect($quiz->weight)->toBe(4)->and($quiz->section_id)->toBe($section->id);

    $this->actingAs($instructor)
        ->postJson("/api/v1/assessment/courses/{$course->slug}/assignments", [
            'title' => 'واجب القسم', 'points' => 50, 'weight' => 2, 'section_id' => $section->id,
        ])
        ->assertCreated();

    $assignment = Assignment::query()->latest('id')->first();
    expect($assignment->weight)->toBe(2)->and($assignment->section_id)->toBe($section->id);
});

// ------------------------------------------------------------- transcript

it('explains transcription requirements when video is not on the managed provider', function () {
    [$instructor, , $section] = authoringCourse();
    $lesson = $section->lessons()->create(['title' => 'مقال', 'type' => 'article', 'position' => 1]);

    $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/lessons/{$lesson->id}/transcript/auto")
        ->assertStatus(422);
});

it('requests transcription from bunny when configured', function () {
    config()->set('video.bunny.api_key', 'test-key');
    config()->set('video.bunny.library_id', '42');
    Http::fake(['video.bunnycdn.com/*' => Http::response(['success' => true], 200)]);

    [$instructor, , $section] = authoringCourse();
    $lesson = $section->lessons()->create([
        'title' => 'فيديو', 'type' => 'video', 'position' => 1,
        'video_provider' => 'bunny', 'video_id' => 'guid-1', 'video_status' => 'ready',
    ]);

    $this->actingAs($instructor)
        ->postJson("/api/v1/catalog/lessons/{$lesson->id}/transcript/auto")
        ->assertStatus(202);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/library/42/videos/guid-1/transcribe')
        && $request->hasHeader('AccessKey', 'test-key'));
});
