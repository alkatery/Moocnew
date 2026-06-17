<?php

declare(strict_types=1);

use App\Contexts\Content\Infrastructure\Persistence\NewsPost;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lists only published posts publicly, newest first', function () {
    NewsPost::factory()->published()->create(['title' => 'خبر قديم', 'published_at' => now()->subDay()]);
    NewsPost::factory()->published()->create(['title' => 'خبر جديد', 'published_at' => now()->subHour()]);
    NewsPost::factory()->create(['title' => 'مسودة']);

    $this->getJson('/api/v1/content/news')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'خبر جديد')
        ->assertJsonPath('data.1.title', 'خبر قديم');
});

it('hides drafts from guests even when include_drafts is requested', function () {
    NewsPost::factory()->create();

    $this->getJson('/api/v1/content/news?include_drafts=1')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('lets staff list drafts with include_drafts', function () {
    NewsPost::factory()->create();
    NewsPost::factory()->published()->create();

    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->getJson('/api/v1/content/news?include_drafts=1')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows a published post by slug to guests', function () {
    $post = NewsPost::factory()->published()->create();

    $this->getJson("/api/v1/content/news/{$post->slug}")
        ->assertOk()
        ->assertJsonPath('data.title', $post->title)
        ->assertJsonPath('data.author.name', $post->author->name);
});

it('returns 404 for an unpublished post unless the viewer manages content', function () {
    $draft = NewsPost::factory()->create();

    $this->getJson("/api/v1/content/news/{$draft->slug}")->assertNotFound();

    Sanctum::actingAs(userWithRole(Role::Supervisor));
    $this->getJson("/api/v1/content/news/{$draft->slug}")->assertOk();
});

it('lets a supervisor create and publish a post', function () {
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson('/api/v1/content/news', [
        'title' => 'انطلاق الفصل الجديد',
        'excerpt' => 'تفاصيل التسجيل في الفصل الجديد.',
        'body' => 'يسرّنا الإعلان عن انطلاق الفصل الدراسي الجديد على المنصة.',
        'published' => true,
    ])->assertCreated()
        ->assertJsonPath('data.title', 'انطلاق الفصل الجديد');

    $post = NewsPost::query()->firstOrFail();
    expect($post->isPublished())->toBeTrue();
    expect($post->slug)->not->toBe('');
});

it('forbids a student from creating a post', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson('/api/v1/content/news', [
        'title' => 'محاولة',
        'body' => 'نص',
        'published' => true,
    ])->assertForbidden();
});

it('lets a supervisor unpublish and update a post', function () {
    $post = NewsPost::factory()->published()->create();
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->patchJson("/api/v1/content/news/{$post->slug}", [
        'title' => 'عنوان محدّث',
        'published' => false,
    ])->assertOk()
        ->assertJsonPath('data.title', 'عنوان محدّث')
        ->assertJsonPath('data.published_at', null);
});

it('lets a supervisor delete a post', function () {
    $post = NewsPost::factory()->create();
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->deleteJson("/api/v1/content/news/{$post->slug}")->assertNoContent();
    $this->assertDatabaseMissing('news_posts', ['id' => $post->id]);
});
