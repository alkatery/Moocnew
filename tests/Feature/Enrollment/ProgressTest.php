<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Build a published course with the given number of article lessons in one
 * section, and return [course, lessons[]].
 */
function courseWithLessons(int $count): array
{
    $course = Course::factory()->published()->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);

    $lessons = [];
    for ($i = 1; $i <= $count; $i++) {
        $lessons[] = $section->lessons()->create([
            'title' => "درس {$i}",
            'type' => 'article',
            'content' => 'محتوى',
            'position' => $i,
        ]);
    }

    return [$course, $lessons];
}

it('records the video resume position for an enrolled learner', function () {
    [$course, $lessons] = courseWithLessons(2);
    $user = User::factory()->create();
    app(EnrollmentService::class)->enroll($user, $course);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/lessons/{$lessons[0]->id}/progress", ['video_position' => 125])
        ->assertOk()
        ->assertJsonPath('progress.video_position', 125);

    $this->assertDatabaseHas('lesson_progress', [
        'lesson_id' => $lessons[0]->id,
        'video_position' => 125,
    ]);
});

it('updates the completion percentage and completes the enrollment when all lessons are done', function () {
    [$course, $lessons] = courseWithLessons(2);
    $user = User::factory()->create();
    app(EnrollmentService::class)->enroll($user, $course);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/lessons/{$lessons[0]->id}/progress", ['completed' => true])
        ->assertOk()
        ->assertJsonPath('enrollment.progress_percent', 50)
        ->assertJsonPath('enrollment.status', EnrollmentStatus::Active->value);

    $this->postJson("/api/v1/lessons/{$lessons[1]->id}/progress", ['completed' => true])
        ->assertOk()
        ->assertJsonPath('enrollment.progress_percent', 100)
        ->assertJsonPath('enrollment.status', EnrollmentStatus::Completed->value);
});

it('refuses to record progress without an active enrollment', function () {
    [, $lessons] = courseWithLessons(1);
    Sanctum::actingAs(User::factory()->create()); // not enrolled

    $this->postJson("/api/v1/lessons/{$lessons[0]->id}/progress", ['completed' => true])
        ->assertForbidden();
});
