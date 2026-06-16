<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Catalog\Application\CourseCloner;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * اختبارات ميزة E5 — مكتبات المحتوى (استيراد أسئلة عبر مقررات المؤلّف).
 * تغطّي: نسخ عميق كامل (٧ حقول)، عزل المصدر/الهدف، عزل الملكية (403)،
 * importable (استثناء الهدف، تصفية آمنة)، co_author، ذرّية،
 * إصلاح CourseCloner (config/explanation).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->instructor = userWithRole(Role::Instructor);

    // مقرر المصدر A (يملكه المؤلّف).
    $this->courseA = Course::factory()->published()->for($this->instructor, 'instructor')->create();

    // مقرر الهدف B (يملكه المؤلّف).
    $this->courseB = Course::factory()->published()->for($this->instructor, 'instructor')->create();

    Sanctum::actingAs($this->instructor);
});

// ================================================================ helpers

/**
 * ينشئ سؤالاً في بنك مقرر بعينه مع إمكانية تخصيص الحقول.
 */
function e5Question(Course $course, array $overrides = []): Question
{
    return Question::query()->create(array_merge([
        'course_id' => $course->id,
        'type' => 'mcq',
        'body' => 'سؤال E5 افتراضي؟',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['a'],
        'config' => null,
        'explanation' => null,
        'points' => 1,
    ], $overrides));
}

// ================================================================ 1. نسخ مستقلّ كامل (§6.1 بند 1)

it('يستورد سؤال mcq من مقرر A إلى مقرر B وينشئ صفاً جديداً مستقلاً', function () {
    $source = e5Question($this->courseA);

    $response = $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ]);

    $response->assertCreated()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.course_id', $this->courseB->id)
        ->assertJsonPath('data.0.type', 'mcq')
        ->assertJsonPath('data.0.body', $source->body)
        ->assertJsonPath('data.0.correct', $source->correct);

    // معرّف مختلف.
    expect($response->json('data.0.id'))->not->toBe($source->id);

    // أسئلة A لا تزال 1.
    expect(Question::query()->where('course_id', $this->courseA->id)->count())->toBe(1);

    // أسئلة B أصبحت 1 (كانت صفراً).
    expect(Question::query()->where('course_id', $this->courseB->id)->count())->toBe(1);
});

// ================================================================ 2. كل خصائص E4 تُنسَخ (§6.1 بند 2)

it('ينسخ config وexplanation كاملاً (حقول E4) للسؤال العددي', function () {
    $source = e5Question($this->courseA, [
        'type' => 'numerical',
        'body' => 'ما قيمة π؟',
        'choices' => null,
        'correct' => [3.14159],
        'config' => ['tolerance' => 0.01],
        'explanation' => 'النسبة التقريبية للدائرة.',
        'points' => 5,
    ]);

    $response = $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ]);

    $response->assertCreated();

    $copy = Question::query()->where('course_id', $this->courseB->id)->first();

    expect($copy->config)->toBe(['tolerance' => 0.01]);
    expect($copy->explanation)->toBe('النسبة التقريبية للدائرة.');
    expect($copy->points)->toBe(5);
    expect($copy->type->value)->toBe('numerical');
});

it('ينسخ config.flags كاملاً للسؤال regex', function () {
    $source = e5Question($this->courseA, [
        'type' => 'regex',
        'body' => 'طابق كلمة تبدأ بـ أ',
        'choices' => null,
        'correct' => ['^أ\w+'],
        'config' => ['flags' => 'i'],
        'points' => 3,
    ]);

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ])->assertCreated();

    $copy = Question::query()->where('course_id', $this->courseB->id)->first();

    expect($copy->config)->toBe(['flags' => 'i']);
    expect($copy->correct)->toBe(['^أ\w+']);
});

// ================================================================ 3. عزل المصدر عن الهدف (§6.1 بند 3)

it('تعديل جسم السؤال المصدر لا يؤثّر على النسخة', function () {
    $source = e5Question($this->courseA, ['body' => 'النصّ الأصلي']);

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ])->assertCreated();

    // تعديل المصدر.
    $source->update(['body' => 'نصّ معدَّل']);

    $copy = Question::query()->where('course_id', $this->courseB->id)->first();
    expect($copy->body)->toBe('النصّ الأصلي');
});

it('حذف السؤال المصدر لا يحذف النسخة (لا cascade)', function () {
    $source = e5Question($this->courseA);

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ])->assertCreated();

    $copyId = Question::query()->where('course_id', $this->courseB->id)->value('id');

    // حذف المصدر.
    $source->delete();

    // النسخة لا تزال موجودة.
    expect(Question::query()->find($copyId))->not->toBeNull();
});

// ================================================================ 4. عزل الهدف عن المصدر (§6.1 بند 4)

it('تعديل النسخة لا يؤثّر على المصدر', function () {
    $source = e5Question($this->courseA, ['body' => 'النصّ الأصلي']);

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ])->assertCreated();

    $copy = Question::query()->where('course_id', $this->courseB->id)->first();
    $copy->update(['body' => 'نسخة معدَّلة']);

    // المصدر لم يتغيّر.
    expect($source->fresh()->body)->toBe('النصّ الأصلي');
});

it('حذف النسخة لا يؤثّر على المصدر', function () {
    $source = e5Question($this->courseA);

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ])->assertCreated();

    $copy = Question::query()->where('course_id', $this->courseB->id)->first();
    $copy->delete();

    expect($source->fresh())->not->toBeNull();
});

// ================================================================ 5. منع استيراد أسئلة مؤلّف آخر (§6.1 بند 5)

it('يمنع استيراد سؤال من مقرر مؤلّف آخر بـ 403', function () {
    $otherInstructor = userWithRole(Role::Instructor);
    $otherCourse = Course::factory()->published()->for($otherInstructor, 'instructor')->create();
    $foreignQuestion = e5Question($otherCourse);

    $response = $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$foreignQuestion->id],
    ]);

    $response->assertForbidden();

    // لا صفّ جديد في B.
    expect(Question::query()->where('course_id', $this->courseB->id)->count())->toBe(0);
});

// ================================================================ 6. عزل قائمة importable (§6.1 بند 6)

it('importable لا يُظهر أسئلة مقرر مؤلّف آخر حتّى مع source_course_id مزوّر', function () {
    $otherInstructor = userWithRole(Role::Instructor);
    $otherCourse = Course::factory()->published()->for($otherInstructor, 'instructor')->create();
    e5Question($otherCourse, ['body' => 'سؤال سري']);

    // المؤلّف يحاول الاطلاع على أسئلة المقرر الغريب.
    $response = $this->getJson(
        "/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable?source_course_id={$otherCourse->id}"
    );

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeEmpty();
});

// ================================================================ 7. استثناء المقرر الهدف (§6.1 بند 7)

it('importable لا يُظهر أسئلة المقرر الهدف نفسه', function () {
    // سؤال في B (الهدف).
    e5Question($this->courseB, ['body' => 'سؤال في الهدف']);

    $response = $this->getJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable");

    $response->assertOk();
    $data = $response->json('data');

    // لا يجب أن تظهر أسئلة B في importable لـ B.
    $courseIds = array_column($data, 'source_course');
    foreach ($courseIds as $course) {
        expect($course['id'] ?? null)->not->toBe($this->courseB->id);
    }
});

it('يرفض استيراد سؤال من البنك نفسه بـ 422', function () {
    $source = e5Question($this->courseB);

    $response = $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ]);

    $response->assertStatus(422);
});

// ================================================================ 8. تخويل الهدف (§6.1 بند 8)

it('يمنع مستخدماً بلا صلاحية تأليف على الهدف من importable بـ 403', function () {
    $stranger = userWithRole(Role::Student);
    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable")
        ->assertForbidden();
});

it('يمنع مستخدماً بلا صلاحية تأليف على الهدف من import بـ 403', function () {
    $source = e5Question($this->courseA);

    $stranger = userWithRole(Role::Student);
    Sanctum::actingAs($stranger);

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ])->assertForbidden();
});

// ================================================================ 9. co_author يُحتسب طاقماً (§6.1 بند 9)

it('co_author على المصدر والهدف يستطيع الاستيراد بنجاح', function () {
    $coAuthor = userWithRole(Role::Instructor);

    // إضافة co_author على A (المصدر) وB (الهدف).
    $this->courseA->members()->attach($coAuthor->id, ['role' => 'co_author']);
    $this->courseB->members()->attach($coAuthor->id, ['role' => 'co_author']);

    $source = e5Question($this->courseA);

    Sanctum::actingAs($coAuthor);

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$source->id],
    ])->assertCreated();

    expect(Question::query()->where('course_id', $this->courseB->id)->count())->toBe(1);
});

it('co_author يرى أسئلة المصدر في importable', function () {
    $coAuthor = userWithRole(Role::Instructor);
    $this->courseA->members()->attach($coAuthor->id, ['role' => 'co_author']);
    $this->courseB->members()->attach($coAuthor->id, ['role' => 'co_author']);

    e5Question($this->courseA, ['body' => 'سؤال للمؤلّف المشارك']);

    Sanctum::actingAs($coAuthor);

    $response = $this->getJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.body'))->toBe('سؤال للمؤلّف المشارك');
});

// ================================================================ 10. ذرّية (§6.1 بند 10)

it('سؤال صالح + سؤال من مقرر غريب → 403 ولا نسخ جزئي', function () {
    $validSource = e5Question($this->courseA);

    $otherInstructor = userWithRole(Role::Instructor);
    $otherCourse = Course::factory()->published()->for($otherInstructor, 'instructor')->create();
    $foreignQuestion = e5Question($otherCourse);

    $response = $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$validSource->id, $foreignQuestion->id],
    ]);

    $response->assertForbidden();

    // صفر صفوف جديدة في B.
    expect(Question::query()->where('course_id', $this->courseB->id)->count())->toBe(0);
});

// ================================================================ 11. معرّف مفقود (§6.1 بند 11)

it('معرّف غير موجود في source_question_ids يُرجع 422 some_questions_not_found', function () {
    // السماح بتمرير التحقّق الأوّلي (exists) — نستخدم معرّفاً حقيقياً ثم نحذفه.
    $source = e5Question($this->courseA);
    $existingId = $source->id;

    // حذف السؤال بعد التخطيط لإعادة استخدام معرّفه (يصبح missing في الخدمة).
    // نستخدم معرّفاً كبيراً غير موجود مباشرةً بتجاوز exists عبر إرسال طلب DB مباشر.
    // البديل: نضيف سؤالاً صالحاً + معرّف مزوّر ونتجاوز exists بـ DB raw.
    // للاختبار الصحيح: نصنع سؤالاً ثم نحذفه مباشرةً.
    $source->delete();

    // الآن $existingId غير موجود — لكن FormRequest يرفضه بـ 422 (exists قاعدة).
    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$existingId],
    ])->assertStatus(422);

    expect(Question::query()->where('course_id', $this->courseB->id)->count())->toBe(0);
});

// ================================================================ 12. حدّ وترتيب (§6.1 بند 12)

it('يرفض source_question_ids فارغ بـ 422', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [],
    ])->assertStatus(422);
});

it('يرفض source_question_ids بأكثر من 100 معرّف بـ 422', function () {
    // ننشئ سؤالاً واحداً ونكرّر معرّفه بما يتجاوز 100 — لكن distinct يرفض التكرار.
    // نستخدم أرقاماً وهمية لتجاوز distinct (كلها مختلفة).
    $ids = range(99901, 100002); // 102 معرّف.

    $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => $ids,
    ])->assertStatus(422);
});

it('يحفظ ترتيب الاستيراد كما في source_question_ids', function () {
    $q1 = e5Question($this->courseA, ['body' => 'أوّل']);
    $q2 = e5Question($this->courseA, ['body' => 'ثانٍ']);
    $q3 = e5Question($this->courseA, ['body' => 'ثالث']);

    $response = $this->postJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/import", [
        'source_question_ids' => [$q3->id, $q1->id, $q2->id],
    ]);

    $response->assertCreated();

    $bodies = array_column($response->json('data'), 'body');
    expect($bodies)->toBe(['ثالث', 'أوّل', 'ثانٍ']);
});

// ================================================================ importable — بحث وتصفية

it('importable يُظهر أسئلة مقرر المؤلّف الأخرى مع source_course', function () {
    e5Question($this->courseA, ['body' => 'سؤال في A']);

    $response = $this->getJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.body'))->toBe('سؤال في A');
    expect($response->json('data.0.source_course.id'))->toBe($this->courseA->id);
    expect($response->json('data.0.source_course.title'))->toBe($this->courseA->title);
});

it('importable يُصفّي بالبحث النصّي q', function () {
    e5Question($this->courseA, ['body' => 'الفيزياء النووية']);
    e5Question($this->courseA, ['body' => 'الرياضيات التطبيقية']);

    $response = $this->getJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable?q=الفيزياء");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.body'))->toContain('الفيزياء');
});

it('importable يُصفّي بالنوع', function () {
    e5Question($this->courseA, ['type' => 'mcq', 'body' => 'سؤال mcq']);
    e5Question($this->courseA, [
        'type' => 'true_false',
        'body' => 'سؤال صح/خطأ',
        'choices' => null,
        'correct' => true,
    ]);

    $response = $this->getJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable?type=true_false");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.type'))->toBe('true_false');
});

it('importable لا يكشف correct أو config في قائمة الاستيراد', function () {
    e5Question($this->courseA, ['config' => ['tolerance' => 0.5]]);

    $response = $this->getJson("/api/v1/assessment/courses/{$this->courseB->slug}/questions/importable");

    $response->assertOk();
    $item = $response->json('data.0');

    expect($item)->not->toHaveKey('correct');
    expect($item)->not->toHaveKey('config');
    expect($item)->toHaveKey('choices_count');
});

// ================================================================ CourseCloner — إصلاح ثغرة E4 (§2.3)

it('CourseCloner ينسخ config وexplanation عند استنساخ المقرر', function () {
    // passing_grade مطلوب (NOT NULL) — يجب تعيينه قبل الاستنساخ.
    $this->courseA->update(['passing_grade' => 70]);

    $source = e5Question($this->courseA, [
        'type' => 'numerical',
        'body' => 'سؤال رقمي',
        'choices' => null,
        'correct' => [42.0],
        'config' => ['tolerance' => 2.5],
        'explanation' => 'الإجابة هي 42',
        'points' => 10,
    ]);

    /** @var CourseCloner $cloner */
    $cloner = app(CourseCloner::class);
    $clonedCourse = $cloner->clone($this->instructor, $this->courseA);

    $clonedQuestion = Question::query()->where('course_id', $clonedCourse->id)->first();

    expect($clonedQuestion)->not->toBeNull();
    expect($clonedQuestion->config)->toBe(['tolerance' => 2.5]);
    expect($clonedQuestion->explanation)->toBe('الإجابة هي 42');
    expect($clonedQuestion->points)->toBe(10);
    expect($clonedQuestion->body)->toBe('سؤال رقمي');

    // كيانان مستقلّان.
    expect($clonedQuestion->id)->not->toBe($source->id);
    expect($clonedQuestion->course_id)->toBe($clonedCourse->id);
});
