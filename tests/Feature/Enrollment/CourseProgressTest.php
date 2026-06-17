<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns saved positions and completion so the player can resume', function () {
    $course = Course::factory()->published()->create(['pricing_type' => 'free', 'price_minor' => 0]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $l1 = $section->lessons()->create(['title' => 'د1', 'type' => 'video', 'video_provider' => 'youtube', 'video_id' => 'x', 'video_status' => 'ready', 'position' => 1]);
    $l2 = $section->lessons()->create(['title' => 'د2', 'type' => 'article', 'content' => 'م', 'position' => 2]);

    $user = User::factory()->create();
    app(EnrollmentService::class)->enroll($user, $course);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/lessons/{$l1->id}/progress", ['video_position' => 90])->assertOk();
    $this->postJson("/api/v1/lessons/{$l2->id}/progress", ['completed' => true])->assertOk();

    $data = $this->getJson("/api/v1/catalog/courses/{$course->slug}/progress")->assertOk()->json('data');

    expect($data['enrolled'])->toBeTrue();
    expect($data['percent'])->toBe(50);
    $byLesson = collect($data['lessons'])->keyBy('lesson_id');
    expect($byLesson[$l1->id]['video_position'])->toBe(90);
    expect($byLesson[$l2->id]['completed'])->toBeTrue();
});

it('reports not-enrolled progress for a guest learner', function () {
    $course = Course::factory()->published()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/catalog/courses/{$course->slug}/progress")
        ->assertOk()
        ->assertJsonPath('data.enrolled', false);
});

it('sorts the catalogue by top rated', function () {
    $popular = Course::factory()->published()->create(['title' => 'الأعلى تقييماً']);
    $other = Course::factory()->published()->create(['title' => 'أخرى']);

    $reviewer = User::factory()->create();
    app(EnrollmentService::class)->enroll($reviewer, $popular);
    Sanctum::actingAs($reviewer);
    $this->postJson("/api/v1/catalog/courses/{$popular->slug}/reviews", ['rating' => 5])->assertCreated();

    $this->getJson('/api/v1/catalog/courses?sort=top_rated')
        ->assertOk()
        ->assertJsonPath('data.0.slug', $popular->slug);
});
