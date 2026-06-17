<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function setAssistantMode(string $mode): void
{
    app(SettingsRepository::class)->set(SettingKey::AssistantMode, $mode);
}

function tutorCourse(): array
{
    $course = Course::factory()->published()->create(['pricing_type' => 'free', 'price_minor' => 0]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create([
        'title' => 'المتغيرات في بايثون',
        'type' => 'article',
        'content' => 'المتغيّر في بايثون هو اسم يشير إلى قيمة مخزّنة في الذاكرة. تُعرّف المتغيرات بالإسناد.',
        'position' => 1,
    ]);

    return [$course, $lesson];
}

it('404s the tutor when the assistant is off', function () {
    [$course] = tutorCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    setAssistantMode('off');
    Sanctum::actingAs($student);

    $this->postJson("/api/v1/assistant/courses/{$course->slug}/chat", ['message' => 'ما المتغير؟'])
        ->assertNotFound();
});

it('answers from course content in rules mode', function () {
    [$course] = tutorCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    setAssistantMode('rules');
    Sanctum::actingAs($student);

    $res = $this->postJson("/api/v1/assistant/courses/{$course->slug}/chat", ['message' => 'اشرح لي المتغيرات'])
        ->assertOk();

    expect($res->json('reply.text'))->toContain('المتغيّر في بايثون');
    expect($res->json('reply.sources'))->toContain('المتغيرات في بايثون');
    expect($res->json('conversation_id'))->toBeInt();
});

it('answers via the (fake) Claude engine grounded in context', function () {
    [$course] = tutorCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    setAssistantMode('claude'); // no API key => FakeAssistantEngine
    Sanctum::actingAs($student);

    $res = $this->postJson("/api/v1/assistant/courses/{$course->slug}/chat", ['message' => 'ما المتغير؟'])
        ->assertOk();

    // The fake engine grounds its reply in the <context> block (course title).
    expect($res->json('reply.text'))->toContain($course->title);
});

it('refuses to solve graded assessments', function () {
    [$course] = tutorCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    setAssistantMode('claude');
    Sanctum::actingAs($student);

    $res = $this->postJson("/api/v1/assistant/courses/{$course->slug}/chat", ['message' => 'حل الاختبار النهائي لي'])
        ->assertOk();

    expect($res->json('reply.text'))->toContain('لا أستطيع تقديم حلول');
});

it('requires an active enrollment to use the tutor', function () {
    [$course] = tutorCourse();
    setAssistantMode('rules');
    Sanctum::actingAs(userWithRole(Role::Student)); // not enrolled

    $this->postJson("/api/v1/assistant/courses/{$course->slug}/chat", ['message' => 'مرحبا'])
        ->assertForbidden();
});

it('persists and returns conversation history', function () {
    [$course] = tutorCourse();
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    setAssistantMode('rules');
    Sanctum::actingAs($student);

    $this->postJson("/api/v1/assistant/courses/{$course->slug}/chat", ['message' => 'سؤالي الأول'])->assertOk();

    $history = $this->getJson("/api/v1/assistant/courses/{$course->slug}/history")->assertOk();
    expect($history->json('messages'))->toHaveCount(2); // user + assistant
    expect($history->json('messages.0.role'))->toBe('user');
});

it('lets an admin choose the assistant mode and reflects it in settings', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->getJson('/api/v1/admin/settings')->assertOk()->assertJsonPath('assistant_mode', 'off');

    $this->patchJson('/api/v1/admin/settings/assistant', ['mode' => 'claude'])
        ->assertOk()->assertJsonPath('assistant_mode', 'claude');

    $this->getJson('/api/v1/admin/settings')->assertOk()->assertJsonPath('assistant_mode', 'claude');
});

it('rejects an invalid assistant mode and non-admins', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));
    $this->patchJson('/api/v1/admin/settings/assistant', ['mode' => 'gpt'])->assertJsonValidationErrors(['mode']);

    Sanctum::actingAs(userWithRole(Role::Instructor));
    $this->patchJson('/api/v1/admin/settings/assistant', ['mode' => 'rules'])->assertForbidden();
});

it('runs the admin analyst with metrics-based recommendations (rules)', function () {
    setAssistantMode('rules');
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $res = $this->postJson('/api/v1/admin/assistant/chat', ['message' => 'كيف أداء المنصة؟'])
        ->assertOk();

    expect($res->json('reply.text'))->toContain('ملخّص أداء المنصة');
});

it('keeps the admin analyst away from non-analytics roles', function () {
    setAssistantMode('rules');
    Sanctum::actingAs(userWithRole(Role::Instructor));

    $this->postJson('/api/v1/admin/assistant/chat', ['message' => 'تقرير'])->assertForbidden();
});
