<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Learning\Infrastructure\Persistence\Bookmark;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * ينشئ مقرراً منشوراً + قسماً + درساً + طالباً ملتحقاً نشطاً.
 * يُعيد [$instructor, $course, $lesson, $student].
 */
function bookmarkFixture(bool $freePreview = false): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $section = $course->sections()->create(['title' => 'قسم التجربة', 'position' => 1]);
    $lesson = $section->lessons()->create([
        'title' => 'درس التجربة',
        'type' => 'video',
        'position' => 1,
        'video_provider' => 'youtube',
        'video_id' => 'test123',
        'video_status' => 'ready',
        'is_free_preview' => $freePreview,
    ]);
    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'progress_percent' => 0,
        'enrolled_at' => now(),
    ]);

    return [$instructor, $course, $lesson, $student];
}

// ---------------------------------------------------------------- §5.1 — إنشاء

it('يُنشئ علامة مرجعية بنجاح ويُعيد 201 مع lesson وcourse.slug', function () {
    [, $course, $lesson, $student] = bookmarkFixture();

    $response = test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertCreated(); // 201

    // تحقّق من شكل الاستجابة §1.أ
    $response
        ->assertJsonPath('data.lesson.id', $lesson->id)
        ->assertJsonPath('data.lesson.title', $lesson->title)
        ->assertJsonPath('data.course.slug', $course->slug)
        ->assertJsonStructure([
            'data' => ['id', 'lesson' => ['id', 'title', 'type'], 'course' => ['id', 'title', 'slug'], 'created_at'],
        ]);

    // تحقّق من وجود الصف في القاعدة
    expect(Bookmark::query()
        ->where('user_id', $student->id)
        ->where('lesson_id', $lesson->id)
        ->exists()
    )->toBeTrue();
});

// ---------------------------------------------------------------- §5.2 — عرض علامات المستخدم

it('يُعيد GET /bookmarks علامات المستخدم المصادَق فقط (الأحدث أولاً) مع lesson وcourse', function () {
    [, , $lesson, $student] = bookmarkFixture();

    // درس ثانٍ في نفس المقرر
    $lesson2 = $lesson->section->lessons()->create([
        'title' => 'درس ثانٍ', 'type' => 'video', 'position' => 2,
        'video_provider' => 'youtube', 'video_id' => 'xyz', 'video_status' => 'ready',
    ]);

    // أنشئ علامتين بفارق زمني للتحقّق من الترتيب
    test()->actingAs($student)->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id]);
    test()->actingAs($student)->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson2->id]);

    $response = test()->actingAs($student)
        ->getJson('/api/v1/bookmarks')
        ->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    // الأحدث أولاً — العلامة الثانية (lesson2) ستظهر أولاً
    expect($data[0]['lesson']['id'])->toBe($lesson2->id);

    // كل عنصر يحمل lesson وcourse
    foreach ($data as $item) {
        expect($item)->toHaveKeys(['id', 'lesson', 'course', 'created_at']);
        expect($item['lesson'])->toHaveKeys(['id', 'title', 'type']);
        expect($item['course'])->toHaveKeys(['id', 'title', 'slug']);
    }
});

// ---------------------------------------------------------------- §5.3 — حذف

it('يحذف العلامة المرجعية ويُعيد 204 بلا جسم', function () {
    [, , $lesson, $student] = bookmarkFixture();

    $bookmarkId = test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertCreated()
        ->json('data.id');

    test()->actingAs($student)
        ->deleteJson("/api/v1/bookmarks/{$bookmarkId}")
        ->assertNoContent(); // 204

    // الصف اختفى من القاعدة
    expect(Bookmark::query()->find($bookmarkId))->toBeNull();
});

// ---------------------------------------------------------------- §5.4 — 403 درس خارج الالتحاق

it('يرفض POST لدرس خارج التحاق المستخدم بـ 403 ولا ينشئ صفّاً', function () {
    [, $course, $lesson] = bookmarkFixture();

    // طالب ليس ملتحقاً بالمقرر
    $outsider = userWithRole(Role::Student);

    test()->actingAs($outsider)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertForbidden(); // 403

    expect(Bookmark::query()
        ->where('user_id', $outsider->id)
        ->where('lesson_id', $lesson->id)
        ->exists()
    )->toBeFalse();
});

// ---------------------------------------------------------------- §5.5 — free-preview مسموح

it('يسمح POST لدرس free-preview حتى بلا التحاق', function () {
    [, $course, $lesson] = bookmarkFixture(freePreview: true);

    // طالب ليس ملتحقاً — لكن الدرس free-preview
    $outsider = userWithRole(Role::Student);

    test()->actingAs($outsider)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertCreated(); // 201 — مسموح عبر canAccess

    expect(Bookmark::query()
        ->where('user_id', $outsider->id)
        ->where('lesson_id', $lesson->id)
        ->exists()
    )->toBeTrue();
});

// ---------------------------------------------------------------- §5.6 — idempotent: تكرار POST = صفّ واحد + 200

it('يُعيد POST مكرّر 200 ولا ينشئ صفّاً جديداً في القاعدة', function () {
    [, , $lesson, $student] = bookmarkFixture();

    // الطلب الأول → 201
    test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertCreated();

    // الطلب الثاني (مكرّر) → 200
    test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertOk(); // 200 — لا 409

    // صفّ واحد فقط في القاعدة (يُثبت قيد unique)
    $count = Bookmark::query()
        ->where('user_id', $student->id)
        ->where('lesson_id', $lesson->id)
        ->count();

    expect($count)->toBe(1);
});

// ---------------------------------------------------------------- §5.7 — عزل علامات المستخدمين

it('يعزل علامات المستخدمين: (أ) لا يرى علامات (ب)، ويُعيد 403 عند محاولة حذفها', function () {
    [, $course, $lesson, $studentA] = bookmarkFixture();

    // طالب ثانٍ ملتحق بنفس المقرر
    $studentB = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $studentB->id,
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'progress_percent' => 0,
        'enrolled_at' => now(),
    ]);

    // (ب) يضع علامة
    $bookmarkId = test()->actingAs($studentB)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertCreated()
        ->json('data.id');

    // (أ) يرى قائمة علاماته فارغة (لا يرى علامة ب)
    test()->actingAs($studentA)
        ->getJson('/api/v1/bookmarks')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // (أ) يحاول حذف علامة (ب) → 403
    test()->actingAs($studentA)
        ->deleteJson("/api/v1/bookmarks/{$bookmarkId}")
        ->assertForbidden(); // 403

    // علامة (ب) لا تزال قائمة
    expect(Bookmark::query()->find($bookmarkId))->not->toBeNull();
});

// ---------------------------------------------------------------- §5.8 — تتالي الحذف عند حذف الدرس

it('يحذف الهجرة علامات الدرس تلقائياً عند حذفه (cascadeOnDelete)', function () {
    [, , $lesson, $student] = bookmarkFixture();

    test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertCreated();

    $bookmarkCount = Bookmark::query()->where('lesson_id', $lesson->id)->count();
    expect($bookmarkCount)->toBe(1);

    // حذف الدرس يجب أن يحذف العلامة تلقائياً عبر cascade
    $lesson->delete();

    expect(Bookmark::query()->where('lesson_id', $lesson->id)->count())->toBe(0);
});

// ---------------------------------------------------------------- §5.8 — تتالي الحذف عند حذف المستخدم

it('يحذف الهجرة علامات المستخدم تلقائياً عند حذفه (cascadeOnDelete) — PDPL', function () {
    [, , $lesson, $student] = bookmarkFixture();

    test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => $lesson->id])
        ->assertCreated();

    $userId = $student->id;
    expect(Bookmark::query()->where('user_id', $userId)->count())->toBe(1);

    // حذف المستخدم يجب أن يحذف علاماته (PDPL) — forceDelete لتجاوز SoftDeletes وإطلاق cascade DB
    $student->forceDelete();

    expect(Bookmark::query()->where('user_id', $userId)->count())->toBe(0);
});

// ---------------------------------------------------------------- §5.9 — 422 تحقّق المدخل

it('يُعيد 422 عند POST بلا lesson_id', function () {
    $student = userWithRole(Role::Student);

    test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', [])
        ->assertUnprocessable() // 422
        ->assertJsonValidationErrors(['lesson_id']);
});

it('يُعيد 422 عند POST بـ lesson_id غير موجود في القاعدة', function () {
    $student = userWithRole(Role::Student);

    test()->actingAs($student)
        ->postJson('/api/v1/bookmarks', ['lesson_id' => 999999])
        ->assertUnprocessable() // 422
        ->assertJsonValidationErrors(['lesson_id']);
});

// ---------------------------------------------------------------- مصادقة إلزامية

it('يرفض الطلبات غير المصادَقة بـ 401', function () {
    test()->getJson('/api/v1/bookmarks')->assertUnauthorized();
    test()->postJson('/api/v1/bookmarks', ['lesson_id' => 1])->assertUnauthorized();
    test()->deleteJson('/api/v1/bookmarks/1')->assertUnauthorized();
});

// ---------------------------------------------------------------- 404 للعلامة غير الموجودة

it('يُعيد 404 عند DELETE لعلامة غير موجودة', function () {
    $student = userWithRole(Role::Student);

    test()->actingAs($student)
        ->deleteJson('/api/v1/bookmarks/999999')
        ->assertNotFound(); // 404
});
