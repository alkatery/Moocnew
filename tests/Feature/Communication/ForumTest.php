<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Communication\Infrastructure\Persistence\ForumPost;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->course = Course::factory()->published()->create();
});

function learnerIn(Course $course): User
{
    $u = User::factory()->create();
    app(EnrollmentService::class)->enroll($u, $course);

    return $u;
}

it('lets an enrolled learner start a thread and reply', function () {
    Sanctum::actingAs(learnerIn($this->course));

    $threadId = $this->postJson("/api/v1/community/courses/{$this->course->slug}/threads", [
        'title' => 'سؤال', 'body' => 'كيف أبدأ؟',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/community/threads/{$threadId}/posts", ['body' => 'رد مفيد'])
        ->assertCreated();

    expect(ForumPost::query()->count())->toBe(2); // initial + reply
});

it('forbids a non-enrolled user from posting', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/community/courses/{$this->course->slug}/threads", [
        'title' => 'x', 'body' => 'y',
    ])->assertForbidden();
});

it('blocks duplicate consecutive posts (anti-spam)', function () {
    $learner = learnerIn($this->course);
    Sanctum::actingAs($learner);
    $threadId = $this->postJson("/api/v1/community/courses/{$this->course->slug}/threads", [
        'title' => 'ت', 'body' => 'أول',
    ])->json('data.id');

    $this->postJson("/api/v1/community/threads/{$threadId}/posts", ['body' => 'مكرر'])->assertCreated();
    $this->postJson("/api/v1/community/threads/{$threadId}/posts", ['body' => 'مكرر'])->assertStatus(422);
});

it('lets a supervisor hide a reported post and hides it from learners', function () {
    $learner = learnerIn($this->course);
    Sanctum::actingAs($learner);
    $threadId = $this->postJson("/api/v1/community/courses/{$this->course->slug}/threads", [
        'title' => 'ت', 'body' => 'محتوى',
    ])->json('data.id');
    $postId = ForumPost::query()->where('thread_id', $threadId)->first()->id;

    // Learner reports it.
    $this->postJson("/api/v1/community/posts/{$postId}/report", ['reason' => 'إساءة'])->assertNoContent();

    // Supervisor hides it.
    Sanctum::actingAs(userWithRole(Role::Supervisor));
    app(EnrollmentService::class); // supervisor participates via Moderate/staff
    $this->postJson("/api/v1/community/posts/{$postId}/hide")->assertOk();

    // Learner no longer sees the hidden post.
    Sanctum::actingAs($learner);
    $posts = $this->getJson("/api/v1/community/threads/{$threadId}")->assertOk()->json('data.posts');
    expect($posts)->toBeEmpty();
});

it('lets a supervisor ban a user from a course forum', function () {
    $learner = learnerIn($this->course);

    Sanctum::actingAs(userWithRole(Role::Supervisor));
    $this->postJson("/api/v1/community/courses/{$this->course->slug}/bans", ['user_id' => $learner->id])
        ->assertNoContent();

    // The banned learner can no longer post.
    Sanctum::actingAs($learner);
    $this->postJson("/api/v1/community/courses/{$this->course->slug}/threads", [
        'title' => 'x', 'body' => 'y',
    ])->assertStatus(422);
});

it('forbids a student from moderating', function () {
    $learner = learnerIn($this->course);
    Sanctum::actingAs($learner);
    $threadId = $this->postJson("/api/v1/community/courses/{$this->course->slug}/threads", [
        'title' => 'ت', 'body' => 'محتوى',
    ])->json('data.id');
    $postId = ForumPost::query()->where('thread_id', $threadId)->first()->id;

    $this->postJson("/api/v1/community/posts/{$postId}/hide")->assertForbidden();
});
