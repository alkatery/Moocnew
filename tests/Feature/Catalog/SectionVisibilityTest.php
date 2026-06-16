<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * E2 — اختبارات Feature لجدولة ظهور الأقسام.
 *
 * يتحقّق من:
 *  - إخفاء القسم المجدول عن الطالب/الزائر في كل نقاط التسليم.
 *  - ظهوره الكامل للطاقم (مالك / مراجع).
 *  - حجب المعاينة المجانية داخل قسم مجدول.
 *  - استثناء المخفيّ من حساب التقدّم.
 *  - إكمال سابق لا يُلغى عند ظهور قسم لاحق.
 *  - التأليف: ضبط visible_from للمالك فقط (403 لغيره).
 */
beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

// ────────────────────────────────────────────────────────────────────────────
// دوال مساعدة محلية
// ────────────────────────────────────────────────────────────────────────────

/**
 * ينشئ مقرراً منشوراً يملكه $owner مع قسمين:
 *  - $visibleSection  : visible_from = null (ظاهر دائماً)
 *  - $scheduledSection: visible_from = غداً  (مجدول)
 * ويُعيد [Course, visible Section, scheduled Section, visible Lesson, scheduled Lesson]
 */
function courseWithScheduledSection(?User $owner = null): array
{
    $owner ??= userWithRole(Role::Instructor);

    /** @var Course $course */
    $course = Course::factory()->for($owner, 'instructor')->published()->create();

    /** @var Section $visibleSection */
    $visibleSection = $course->sections()->create([
        'title' => 'القسم الظاهر',
        'position' => 1,
        'visible_from' => null,
    ]);

    /** @var Section $scheduledSection */
    $scheduledSection = $course->sections()->create([
        'title' => 'القسم المجدول',
        'position' => 2,
        'visible_from' => now()->addDay(), // مستقبليّ
    ]);

    $visibleLesson = $visibleSection->lessons()->create([
        'title' => 'درس ظاهر',
        'type' => 'article',
        'content' => 'محتوى',
        'position' => 1,
    ]);

    $scheduledLesson = $scheduledSection->lessons()->create([
        'title' => 'درس مجدول',
        'type' => 'article',
        'content' => 'محتوى سري',
        'position' => 1,
    ]);

    return [$course, $visibleSection, $scheduledSection, $visibleLesson, $scheduledLesson];
}

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 1 + 3: إخفاء القسم المجدول عن الطالب/الزائر في show
// ────────────────────────────────────────────────────────────────────────────

it('يخفي القسم المجدول مستقبلاً عن الزائر في show', function () {
    [$course, $visibleSection, , $visibleLesson] = courseWithScheduledSection();

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    $sections = $response->json('data.sections');
    $ids = array_column($sections, 'id');

    expect($ids)->toContain($visibleSection->id)
        ->and($ids)->not->toContain(
            Course::factory()->published()->create()->sections()->create(['title' => 'x', 'position' => 1])->id
                ?? array_column($sections, 'id')[999] ?? -1, // dummy — نتحقق أدناه
        );

    // التحقق الصريح: المجدول غائب
    $sectionTitles = array_column($sections, 'title');
    expect($sectionTitles)->not->toContain('القسم المجدول');
    expect($sectionTitles)->toContain('القسم الظاهر');
});

it('يخفي القسم المجدول عن الطالب الملتحق في show', function () {
    [$course, $visibleSection, $scheduledSection] = courseWithScheduledSection();

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    $ids = array_column($response->json('data.sections'), 'id');
    expect($ids)->toContain($visibleSection->id)
        ->and($ids)->not->toContain($scheduledSection->id);
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 2: الطاقم يرى كل الأقسام في show
// ────────────────────────────────────────────────────────────────────────────

it('يعرض كل الأقسام للمالك (طاقم) في show بما فيها المجدولة', function () {
    $owner = userWithRole(Role::Instructor);
    [$course, $visibleSection, $scheduledSection] = courseWithScheduledSection($owner);
    Sanctum::actingAs($owner);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    $ids = array_column($response->json('data.sections'), 'id');
    expect($ids)->toContain($visibleSection->id)
        ->and($ids)->toContain($scheduledSection->id);
});

it('يُضمِن القسم المجدول visible_from في استجابة الطاقم', function () {
    $owner = userWithRole(Role::Instructor);
    [$course, , $scheduledSection] = courseWithScheduledSection($owner);
    Sanctum::actingAs($owner);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    $scheduled = collect($response->json('data.sections'))
        ->firstWhere('id', $scheduledSection->id);

    expect($scheduled['visible_from'])->not->toBeNull();
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 3: القسم بتاريخ ماضٍ أو null ظاهر للطالب
// ────────────────────────────────────────────────────────────────────────────

it('يُظهر القسم بـ visible_from في الماضي للطالب', function () {
    $course = Course::factory()->published()->create();

    $pastSection = $course->sections()->create([
        'title' => 'قسم ماضٍ',
        'position' => 1,
        'visible_from' => now()->subDay(),
    ]);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    $ids = array_column($response->json('data.sections'), 'id');
    expect($ids)->toContain($pastSection->id);
});

it('يُظهر القسم بـ visible_from = null للطالب (ظاهر دائماً)', function () {
    $course = Course::factory()->published()->create();

    $section = $course->sections()->create([
        'title' => 'قسم مفتوح',
        'position' => 1,
        'visible_from' => null,
    ]);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    expect(array_column($response->json('data.sections'), 'id'))->toContain($section->id);
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 4: حجب محتوى الدرس (LessonContentController)
// ────────────────────────────────────────────────────────────────────────────

it('يرفض محتوى درس ضمن قسم مجدول للطالب الملتحق بـ 403', function () {
    [$course, , , , $scheduledLesson] = courseWithScheduledSection();

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $this->getJson("/api/v1/lessons/{$scheduledLesson->id}/content")->assertForbidden();
});

it('يُتيح محتوى درس ضمن قسم ظاهر للطالب الملتحق', function () {
    [$course, , , $visibleLesson] = courseWithScheduledSection();

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $this->getJson("/api/v1/lessons/{$visibleLesson->id}/content")->assertOk();
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 5: حجب التشغيل (PlaybackController)
// ────────────────────────────────────────────────────────────────────────────

it('يرفض تشغيل فيديو ضمن قسم مجدول للطالب الملتحق بـ 403', function () {
    config()->set('video.bunny', ['library_id' => '99', 'token_key' => 'k', 'embed_host' => 'iframe.mediadelivery.net']);

    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();

    $scheduledSection = $course->sections()->create([
        'title' => 'قسم فيديو مجدول',
        'position' => 1,
        'visible_from' => now()->addDay(),
    ]);

    $videoLesson = $scheduledSection->lessons()->create([
        'title' => 'فيديو مجدول',
        'type' => 'video',
        'video_provider' => 'bunny',
        'video_id' => 'guid-secret',
        'video_status' => 'ready',
        'position' => 1,
    ]);

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $this->getJson("/api/v1/lessons/{$videoLesson->id}/playback")->assertForbidden();
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 6: حجب المعاينة المجانية داخل قسم مجدول (حرج)
// ────────────────────────────────────────────────────────────────────────────

it('يحجب معاينة مجانية داخل قسم مجدول عن المستخدم غير الملتحق وغير الطاقم (لا تسريب رغم is_free_preview)', function () {
    $course = Course::factory()->published()->create();

    $scheduledSection = $course->sections()->create([
        'title' => 'قسم مجدول فيه معاينة',
        'position' => 1,
        'visible_from' => now()->addDay(),
    ]);

    // درس معاينة مجانية داخل قسم مجدول — يجب أن يُحجَب حتى رغم is_free_preview
    $previewLesson = $scheduledSection->lessons()->create([
        'title' => 'معاينة مجانية مجدولة',
        'type' => 'article',
        'content' => 'محتوى سري',
        'position' => 1,
        'is_free_preview' => true,
    ]);

    // مستخدم مصادق لكن ليس طاقماً ولا ملتحقاً — المسار يتطلب auth:sanctum
    // الجدولة تتقدّم على is_free_preview فيُرفض بـ 403
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->getJson("/api/v1/lessons/{$previewLesson->id}/content")->assertForbidden();
});

it('يحجب معاينة مجانية في قسم مجدول عن الطالب الملتحق', function () {
    $course = Course::factory()->published()->create();

    $scheduledSection = $course->sections()->create([
        'title' => 'قسم مجدول فيه معاينة',
        'position' => 1,
        'visible_from' => now()->addDay(),
    ]);

    $previewLesson = $scheduledSection->lessons()->create([
        'title' => 'معاينة مجانية مجدولة',
        'type' => 'article',
        'content' => 'محتوى سري',
        'position' => 1,
        'is_free_preview' => true,
    ]);

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $this->getJson("/api/v1/lessons/{$previewLesson->id}/content")->assertForbidden();
});

it('يُتيح معاينة مجانية في قسم مجدول للطاقم (المالك)', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();

    $scheduledSection = $course->sections()->create([
        'title' => 'قسم مجدول',
        'position' => 1,
        'visible_from' => now()->addDay(),
    ]);

    $previewLesson = $scheduledSection->lessons()->create([
        'title' => 'معاينة في قسم مجدول',
        'type' => 'article',
        'content' => 'محتوى',
        'position' => 1,
        'is_free_preview' => true,
    ]);

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/lessons/{$previewLesson->id}/content")->assertOk();
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 7: حجب كتابة التقدّم على درس مجدول
// ────────────────────────────────────────────────────────────────────────────

it('يرفض تسجيل تقدّم على درس في قسم مجدول بـ 403 ولا يخزّن سجلاً', function () {
    [$course, , , , $scheduledLesson] = courseWithScheduledSection();

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    $this->postJson("/api/v1/lessons/{$scheduledLesson->id}/progress", ['completed' => true])
        ->assertForbidden();

    $this->assertDatabaseMissing('lesson_progress', ['lesson_id' => $scheduledLesson->id]);
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 8: التقدّم يستثني المخفيّ — المقام = الظاهر فقط
// ────────────────────────────────────────────────────────────────────────────

it('يحسب التقدّم 100% بإنهاء الأقسام الظاهرة دون الأقسام المجدولة', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();

    // 4 دروس ظاهرة
    $visibleSection = $course->sections()->create([
        'title' => 'قسم ظاهر',
        'position' => 1,
        'visible_from' => null,
    ]);

    $visibleLessons = [];
    for ($i = 1; $i <= 4; $i++) {
        $visibleLessons[] = $visibleSection->lessons()->create([
            'title' => "درس ظاهر {$i}",
            'type' => 'article',
            'content' => 'محتوى',
            'position' => $i,
        ]);
    }

    // 2 دروس ضمن قسم مجدول
    $scheduledSection = $course->sections()->create([
        'title' => 'قسم مجدول',
        'position' => 2,
        'visible_from' => now()->addDay(),
    ]);

    for ($i = 1; $i <= 2; $i++) {
        $scheduledSection->lessons()->create([
            'title' => "درس مجدول {$i}",
            'type' => 'article',
            'content' => 'محتوى',
            'position' => $i,
        ]);
    }

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    // إكمال الـ 4 دروس الظاهرة
    foreach ($visibleLessons as $lesson) {
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", ['completed' => true])
            ->assertOk();
    }

    // الاستجابة الأخيرة تحمل 100% (المقام = 4 لا 6)
    $response = $this->postJson(
        "/api/v1/lessons/{$visibleLessons[3]->id}/progress",
        ['completed' => true],
    )->assertOk();

    expect($response->json('enrollment.progress_percent'))->toBe(100);
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 9: ظهور قسم لاحق يعيد الحساب؛ إكمال سابق لا يُلغى
// ────────────────────────────────────────────────────────────────────────────

it('يُعيد حساب التقدّم عند ظهور قسم مجدول ولا يُلغي الإكمال السابق', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();

    $visibleSection = $course->sections()->create([
        'title' => 'قسم ظاهر',
        'position' => 1,
        'visible_from' => null,
    ]);

    $visibleLessons = [];
    for ($i = 1; $i <= 4; $i++) {
        $visibleLessons[] = $visibleSection->lessons()->create([
            'title' => "درس ظاهر {$i}",
            'type' => 'article',
            'content' => 'محتوى',
            'position' => $i,
        ]);
    }

    /** @var Section $scheduledSection */
    $scheduledSection = $course->sections()->create([
        'title' => 'قسم سيظهر لاحقاً',
        'position' => 2,
        'visible_from' => now()->addDay(),
    ]);

    $scheduledLessons = [];
    for ($i = 1; $i <= 2; $i++) {
        $scheduledLessons[] = $scheduledSection->lessons()->create([
            'title' => "درس مجدول {$i}",
            'type' => 'article',
            'content' => 'محتوى',
            'position' => $i,
        ]);
    }

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    // الطالب يُكمل كل الظاهر ⇒ 100% ⇒ Completed
    foreach ($visibleLessons as $lesson) {
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", ['completed' => true])->assertOk();
    }

    // نتحقّق أن الالتحاق أُكمِل (E1 لا يتأثّر)
    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'course_id' => $course->id,
        'progress_percent' => 100,
    ]);

    // الآن نجعل القسم المجدول ظاهراً (تاريخ ماضٍ)
    $scheduledSection->update(['visible_from' => now()->subMinute()]);

    // الطالب يُسجّل تقدّم في الأقسام الجديدة (مقام الآن = 6)
    Sanctum::actingAs($student);
    $response = $this->postJson(
        "/api/v1/lessons/{$scheduledLessons[0]->id}/progress",
        ['completed' => true],
    )->assertOk();

    // progress_percent = floor(5/6 * 100) = 83
    expect($response->json('enrollment.progress_percent'))->toBe(83);

    // الإكمال السابق لم يُلغَ — الحالة ما زالت Completed
    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'completed',
    ]);
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 10 + 11 + 12: التأليف — ضبط visible_from
// ────────────────────────────────────────────────────────────────────────────

it('يقبل المالك PATCH على visible_from ويحفظه ويُعيده في SectionResource', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);

    Sanctum::actingAs($owner);

    $futureDate = now()->addDays(7)->toIso8601String();

    $this->patchJson("/api/v1/catalog/sections/{$section->id}", [
        'visible_from' => $futureDate,
    ])->assertOk()
        ->assertJsonStructure(['data' => ['visible_from']]);

    // التحقّق من قاعدة البيانات مباشرةً
    expect($section->fresh()->visible_from)->not->toBeNull();
});

it('يرفض غير المالك PATCH على visible_from بـ 403', function () {
    $owner = userWithRole(Role::Instructor);
    $other = userWithRole(Role::Student);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);

    Sanctum::actingAs($other);

    $this->patchJson("/api/v1/catalog/sections/{$section->id}", [
        'visible_from' => now()->addDays(3)->toIso8601String(),
    ])->assertForbidden();
});

it('يُلغي الجدولة بإرسال visible_from = null (إلغاء صريح يصفّر الحقل)', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();

    // قسم مجدول
    $section = $course->sections()->create([
        'title' => 'قسم مجدول',
        'position' => 1,
        'visible_from' => now()->addDay(),
    ]);

    Sanctum::actingAs($owner);

    // إلغاء الجدولة
    $this->patchJson("/api/v1/catalog/sections/{$section->id}", [
        'visible_from' => null,
    ])->assertOk()
        ->assertJsonPath('data.visible_from', null);

    // الآن يجب أن يظهر للطالب (مستخدم آخر لا علاقة له بالمقرر)
    Sanctum::actingAs(userWithRole(Role::Student));
    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    expect(array_column($response->json('data.sections'), 'id'))->toContain($section->id);
});

it('يرفض visible_from بصيغة تاريخ غير صالحة بـ 422', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->published()->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/catalog/sections/{$section->id}", [
        'visible_from' => 'ليس-تاريخاً',
    ])->assertUnprocessable();
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 13: الترقية لا تخفي الأقسام القائمة (visible_from = null)
// ────────────────────────────────────────────────────────────────────────────

it('يُظهر الأقسام القائمة (visible_from = null) للطالب بعد الهجرة', function () {
    $course = Course::factory()->published()->create();

    // قسم بلا visible_from (يحاكي الأقسام القائمة قبل الهجرة)
    $section = $course->sections()->create([
        'title' => 'قسم قائم',
        'position' => 1,
        // visible_from غير محدد ⇒ null
    ]);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertOk();

    expect(array_column($response->json('data.sections'), 'id'))->toContain($section->id);
});

// ────────────────────────────────────────────────────────────────────────────
// §5 / المعيار 15: الطلب المباشر بالـ id لا يتجاوز الجدولة
// ────────────────────────────────────────────────────────────────────────────

it('يرفض الطلب المباشر بـ id درس في قسم مجدول بـ 403 للمحتوى والتشغيل والتقدّم', function () {
    config()->set('video.bunny', ['library_id' => '99', 'token_key' => 'k', 'embed_host' => 'iframe.mediadelivery.net']);

    $course = Course::factory()->published()->create();

    $scheduledSection = $course->sections()->create([
        'title' => 'قسم مجدول',
        'position' => 1,
        'visible_from' => now()->addDay(),
    ]);

    // درس مقالة
    $articleLesson = $scheduledSection->lessons()->create([
        'title' => 'مقالة مجدولة',
        'type' => 'article',
        'content' => 'محتوى سري',
        'position' => 1,
    ]);

    // درس فيديو
    $videoLesson = $scheduledSection->lessons()->create([
        'title' => 'فيديو مجدول',
        'type' => 'video',
        'video_provider' => 'bunny',
        'video_id' => 'guid-direct',
        'video_status' => 'ready',
        'position' => 2,
    ]);

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    // جميع نقاط التسليم ترفض رغم معرفة الـ id
    $this->getJson("/api/v1/lessons/{$articleLesson->id}/content")->assertForbidden();
    $this->getJson("/api/v1/lessons/{$videoLesson->id}/playback")->assertForbidden();
    $this->postJson("/api/v1/lessons/{$articleLesson->id}/progress", ['completed' => true])->assertForbidden();
});
