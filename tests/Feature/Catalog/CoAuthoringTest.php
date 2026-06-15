<?php

declare(strict_types=1);

/**
 * اختبارات Feature — E3: التأليف الجماعي (Co-authoring).
 *
 * تغطّي معايير القبول §6 من العقد:
 *  - التحرير (update) لـ co-author.
 *  - الطاقم (isStaffFor) لـ co-author: gradebook / submissions.
 *  - منع التصعيد: co-author لا يدير الأعضاء ولا يحذف ولا ينشر.
 *  - إدارة المالك: إضافة/إزالة؛ قواعد 422.
 *  - mine يشمل مقررات co-author.
 *  - عزل المقررات: co-author في A لا يحرّر B.
 *  - بحث المدرّسين (manageMembers فقط).
 */

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

// ────────────────────────────────────────────────────────────────────────────
// دوال مساعدة محلية
// ────────────────────────────────────────────────────────────────────────────

/**
 * ينشئ مالكاً ومقرراً ومؤلّفاً مشاركاً ويربطهما.
 * يُعيد [Course $course, User $owner, User $coAuthor].
 */
function coAuthorScenario(): array
{
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create(); // مسودّة

    $coAuthor = userWithRole(Role::Instructor);
    $course->members()->attach($coAuthor->id, ['role' => 'co_author']);

    return [$course, $owner, $coAuthor];
}

/**
 * ينشئ قسماً بسيطاً للمقرر.
 */
function createSection(Course $course, int $position = 1): Section
{
    return $course->sections()->create([
        'title' => 'قسم اختبار '.$position,
        'position' => $position,
    ]);
}

// ════════════════════════════════════════════════════════════════════════════
// §1 — إدارة المالك: إضافة / إزالة الأعضاء
// ════════════════════════════════════════════════════════════════════════════

it('المالك يرى قائمة الأعضاء فارغة عند البداية', function () {
    [$course, $owner] = coAuthorScenario(); // يُنشئ co-author مسبقاً
    // مقرر جديد بلا أعضاء
    $empty = Course::factory()->for($owner, 'instructor')->create();

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/catalog/courses/{$empty->slug}/members")
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('المالك يضيف مدرّساً عضواً ويُعاد 201 بالقائمة', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    $newCoAuthor = userWithRole(Role::Instructor);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/members", [
        'user_id' => $newCoAuthor->id,
    ])->assertCreated()
        ->assertJsonPath('data.0.id', $newCoAuthor->id)
        ->assertJsonPath('data.0.role', 'co_author');

    $this->assertDatabaseHas('course_members', [
        'course_id' => $course->id,
        'user_id' => $newCoAuthor->id,
        'role' => 'co_author',
    ]);
});

it('المالك يزيل عضواً ويُعاد 204 (idempotent)', function () {
    [$course, $owner, $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($owner);

    // الإزالة الأولى
    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}/members/{$coAuthor->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('course_members', [
        'course_id' => $course->id,
        'user_id' => $coAuthor->id,
    ]);

    // الإزالة الثانية — idempotent: 204 ولا استثناء
    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}/members/{$coAuthor->id}")
        ->assertNoContent();
});

it('إضافة عضو مكرّر تُعيد 200 (idempotent) بالقائمة الحالية', function () {
    [$course, $owner, $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($owner);

    // إضافة نفس العضو مرة ثانية
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/members", [
        'user_id' => $coAuthor->id,
    ])->assertOk()
        ->assertJsonPath('data.0.id', $coAuthor->id);

    // سجل واحد فقط في قاعدة البيانات
    $this->assertDatabaseCount('course_members', 1);
});

it('إضافة غير مدرّس ترفض بـ 422', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    $student = userWithRole(Role::Student);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/members", [
        'user_id' => $student->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

it('إضافة المالك نفسه ترفض بـ 422', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/members", [
        'user_id' => $owner->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

// ════════════════════════════════════════════════════════════════════════════
// §2 — منع التصعيد: co-author لا يدير الأعضاء
// ════════════════════════════════════════════════════════════════════════════

it('co-author يحاول GET /members فيُرفض بـ 403', function () {
    [$course, , $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($coAuthor);

    $this->getJson("/api/v1/catalog/courses/{$course->slug}/members")
        ->assertForbidden();
});

it('co-author يحاول POST /members فيُرفض بـ 403', function () {
    [$course, , $coAuthor] = coAuthorScenario();
    $other = userWithRole(Role::Instructor);

    Sanctum::actingAs($coAuthor);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/members", [
        'user_id' => $other->id,
    ])->assertForbidden();
});

it('co-author يحاول DELETE /members/{user} فيُرفض بـ 403', function () {
    [$course, , $coAuthor] = coAuthorScenario();
    $other = userWithRole(Role::Instructor);
    $course->members()->attach($other->id, ['role' => 'co_author']);

    Sanctum::actingAs($coAuthor);

    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}/members/{$other->id}")
        ->assertForbidden();
});

it('المراجع يحاول POST /members فيُرفض بـ 403 (ليس مالكاً)', function () {
    [$course] = coAuthorScenario();
    $reviewer = userWithRole(Role::Supervisor); // لديه courses.review
    $other = userWithRole(Role::Instructor);

    Sanctum::actingAs($reviewer);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/members", [
        'user_id' => $other->id,
    ])->assertForbidden();
});

// ════════════════════════════════════════════════════════════════════════════
// §3 — co-author يحرّر المحتوى (update) — §2.ب من العقد
// ════════════════════════════════════════════════════════════════════════════

it('co-author يُنشئ قسماً جديداً (201)', function () {
    [$course, , $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($coAuthor);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/sections", [
        'title' => 'قسم جديد من co-author',
    ])->assertCreated();

    $this->assertDatabaseHas('sections', ['course_id' => $course->id, 'title' => 'قسم جديد من co-author']);
});

it('co-author يعدّل قسماً موجوداً (200)', function () {
    [$course, , $coAuthor] = coAuthorScenario();
    $section = createSection($course);

    Sanctum::actingAs($coAuthor);

    $this->patchJson("/api/v1/catalog/sections/{$section->id}", [
        'title' => 'عنوان محدّث',
    ])->assertOk();
});

it('co-author يحذف قسماً (204)', function () {
    [$course, , $coAuthor] = coAuthorScenario();
    $section = createSection($course);

    Sanctum::actingAs($coAuthor);

    $this->deleteJson("/api/v1/catalog/sections/{$section->id}")
        ->assertNoContent();
});

it('co-author يفتح مسودّة المقرر في الاستوديو (200)', function () {
    [$course, , $coAuthor] = coAuthorScenario(); // المقرر مسودّة

    Sanctum::actingAs($coAuthor);

    $this->getJson("/api/v1/catalog/courses/{$course->slug}")
        ->assertOk();
});

// ════════════════════════════════════════════════════════════════════════════
// §4 — منع التصعيد: co-author لا يحذف المقرر ولا ينشر
// ════════════════════════════════════════════════════════════════════════════

it('co-author يحاول حذف المقرر فيُرفض بـ 403', function () {
    [$course, , $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($coAuthor);

    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}")
        ->assertForbidden();
});

it('co-author يحاول إرسال المقرر للمراجعة فيُرفض بـ 403', function () {
    [$course, , $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($coAuthor);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/submit")
        ->assertForbidden();
});

// ════════════════════════════════════════════════════════════════════════════
// §5 — co-author = طاقم المقرر (isStaffFor) — gradebook / مسودّة
// ════════════════════════════════════════════════════════════════════════════

it('co-author يرى gradebook المقرر (200)', function () {
    [$course, , $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($coAuthor);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();
});

it('مستخدم بلا علاقة لا يرى gradebook (403)', function () {
    [$course] = coAuthorScenario();
    $stranger = userWithRole(Role::Instructor); // مدرّس آخر بلا علاقة

    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertForbidden();
});

// ════════════════════════════════════════════════════════════════════════════
// §6 — mine يشمل مقررات co-author
// ════════════════════════════════════════════════════════════════════════════

it('mine يشمل مقررات co-author ولا يشمل مقررات لا علاقة بها', function () {
    $coAuthor = userWithRole(Role::Instructor);

    // مقرر هو co-author فيه
    $owner = userWithRole(Role::Instructor);
    $memberCourse = Course::factory()->for($owner, 'instructor')->create(['title' => 'مقرر العضوية']);
    $memberCourse->members()->attach($coAuthor->id, ['role' => 'co_author']);

    // مقرر لا علاقة له به
    $other = userWithRole(Role::Instructor);
    Course::factory()->for($other, 'instructor')->create(['title' => 'مقرر آخر']);

    Sanctum::actingAs($coAuthor);

    $response = $this->getJson('/api/v1/catalog/mine')->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('مقرر العضوية')
        ->and($titles)->not->toContain('مقرر آخر');
});

it('mine يشمل مقررات co-author بجانب مقررات المالك', function () {
    $instructor = userWithRole(Role::Instructor);

    // مقرر يملكه
    $ownedCourse = Course::factory()->for($instructor, 'instructor')->create(['title' => 'مقرري']);

    // مقرر هو co-author فيه
    $owner2 = userWithRole(Role::Instructor);
    $coAuthCourse = Course::factory()->for($owner2, 'instructor')->create(['title' => 'مقرر مشترك']);
    $coAuthCourse->members()->attach($instructor->id, ['role' => 'co_author']);

    Sanctum::actingAs($instructor);

    $response = $this->getJson('/api/v1/catalog/mine')->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('مقرري')
        ->and($titles)->toContain('مقرر مشترك');
});

// ════════════════════════════════════════════════════════════════════════════
// §7 — عزل المقررات: co-author في A لا يحرّر B
// ════════════════════════════════════════════════════════════════════════════

it('co-author في المقرر A لا يحرّر المقرر B (403)', function () {
    // المقرر A
    $ownerA = userWithRole(Role::Instructor);
    $courseA = Course::factory()->for($ownerA, 'instructor')->create();
    $coAuthor = userWithRole(Role::Instructor);
    $courseA->members()->attach($coAuthor->id, ['role' => 'co_author']);

    // المقرر B (مالك مختلف)
    $ownerB = userWithRole(Role::Instructor);
    $courseB = Course::factory()->for($ownerB, 'instructor')->create();

    Sanctum::actingAs($coAuthor);

    // يحاول إنشاء قسم في المقرر B
    $this->postJson("/api/v1/catalog/courses/{$courseB->slug}/sections", [
        'title' => 'محاولة تعدٍّ',
    ])->assertForbidden();
});

it('co-author في A لا يرى gradebook B (403)', function () {
    $ownerA = userWithRole(Role::Instructor);
    $courseA = Course::factory()->for($ownerA, 'instructor')->create();
    $coAuthor = userWithRole(Role::Instructor);
    $courseA->members()->attach($coAuthor->id, ['role' => 'co_author']);

    $ownerB = userWithRole(Role::Instructor);
    $courseB = Course::factory()->for($ownerB, 'instructor')->create();

    Sanctum::actingAs($coAuthor);

    $this->getJson("/api/v1/assessment/courses/{$courseB->slug}/gradebook")
        ->assertForbidden();
});

// ════════════════════════════════════════════════════════════════════════════
// §8 — بعد الإزالة: الصلاحيات تسقط فوراً
// ════════════════════════════════════════════════════════════════════════════

it('بعد إزالة co-author محاولة تحرير القسم تُعيد 403', function () {
    [$course, $owner, $coAuthor] = coAuthorScenario();
    $section = createSection($course);

    // التحقّق أن التحرير كان مسموحاً قبل الإزالة
    Sanctum::actingAs($coAuthor);
    $this->patchJson("/api/v1/catalog/sections/{$section->id}", ['title' => 'قبل الإزالة'])
        ->assertOk();

    // إزالة العضو
    Sanctum::actingAs($owner);
    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}/members/{$coAuthor->id}")
        ->assertNoContent();

    // التحقّق أن التحرير محظور بعد الإزالة
    Sanctum::actingAs($coAuthor);
    $this->patchJson("/api/v1/catalog/sections/{$section->id}", ['title' => 'بعد الإزالة'])
        ->assertForbidden();
});

// ════════════════════════════════════════════════════════════════════════════
// §9 — بحث المدرّسين للـ picker
// ════════════════════════════════════════════════════════════════════════════

it('المالك يبحث عن مدرّسين ويرى النتائج (id/name فقط)', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    $instructor = userWithRole(Role::Instructor);
    $instructor->forceFill(['name' => 'فاطمة الزهراني'])->save();

    Sanctum::actingAs($owner);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}/instructors?q=فاطمة")
        ->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray()
        ->and($data[0]['name'])->toBe('فاطمة الزهراني')
        ->and($data[0])->toHaveKey('id')
        ->and($data[0])->not->toHaveKey('email');
});

it('البحث يستثني الأعضاء الحاليين والمالك', function () {
    [$course, $owner, $coAuthor] = coAuthorScenario();
    $coAuthor->forceFill(['name' => 'سارة المستثناة'])->save();
    $owner->forceFill(['name' => 'أحمد المستثنى'])->save();

    Sanctum::actingAs($owner);

    // بحث بنصّ قصير يُعيد الجميع (لكن بعد الفلترة لا يجد سارة وأحمد)
    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}/instructors?q=سارة")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($coAuthor->id)
        ->and($ids)->not->toContain($owner->id);
});

it('co-author يحاول بحث المدرّسين فيُرفض بـ 403', function () {
    [$course, , $coAuthor] = coAuthorScenario();

    Sanctum::actingAs($coAuthor);

    $this->getJson("/api/v1/catalog/courses/{$course->slug}/instructors?q=فاطمة")
        ->assertForbidden();
});

it('البحث يتطلّب q بحرفين على الأقل', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();

    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/catalog/courses/{$course->slug}/instructors?q=ف")
        ->assertStatus(422);
});
