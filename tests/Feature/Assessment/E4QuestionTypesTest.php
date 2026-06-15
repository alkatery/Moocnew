<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * اختبارات ميزة E4 — أنواع أسئلة إضافية.
 * تغطّي: التأليف (201/422)، الأداء (تصحيح)، الأمان (حجب correct/config).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->instructor = userWithRole(Role::Instructor);
    $this->course = Course::factory()->published()->for($this->instructor, 'instructor')->create();
    Sanctum::actingAs($this->instructor);
});

// ================================================================ helpers

function e4Question(Course $course, array $overrides = []): Question
{
    return Question::query()->create(array_merge([
        'course_id' => $course->id,
        'type' => 'mcq',
        'body' => 'سؤال E4؟',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['a'],
        'config' => null,
        'points' => 1,
    ], $overrides));
}

function e4Quiz(Course $course, array $questions): Quiz
{
    $quiz = Quiz::query()->create([
        'course_id' => $course->id,
        'title' => 'اختبار E4',
        'pass_mark' => 100,
        'shuffle' => false,
    ]);
    $sync = [];
    foreach ($questions as $i => $q) {
        $sync[$q->id] = ['position' => $i + 1];
    }
    $quiz->questions()->sync($sync);

    return $quiz;
}

// ================================================================ dropdown — التأليف

it('creates a dropdown question with a single correct answer', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'dropdown',
        'body' => 'اختر العاصمة',
        'choices' => [['id' => 'a', 'text' => 'الرياض'], ['id' => 'b', 'text' => 'جدة']],
        'correct' => ['a'],
    ])->assertCreated()
        ->assertJsonPath('data.type', 'dropdown')
        ->assertJsonPath('data.correct', ['a'])
        ->assertJsonPath('data.config', null);
});

it('rejects dropdown with two correct answers (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'dropdown',
        'body' => 'سؤال',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['a', 'b'],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

it('rejects dropdown with empty correct array (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'dropdown',
        'body' => 'سؤال',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => [],
    ])->assertStatus(422);
});

it('rejects dropdown with correct id not in choices (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'dropdown',
        'body' => 'سؤال',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['z'],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

// ================================================================ multi_select — التأليف

it('creates a multi_select question with multiple correct answers', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'multi_select',
        'body' => 'اختر الصحيح',
        'choices' => [
            ['id' => 'a', 'text' => 'أ'],
            ['id' => 'b', 'text' => 'ب'],
            ['id' => 'c', 'text' => 'ج'],
        ],
        'correct' => ['a', 'c'],
    ])->assertCreated()
        ->assertJsonPath('data.type', 'multi_select')
        ->assertJsonPath('data.correct', ['a', 'c']);
});

it('rejects multi_select with empty correct array (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'multi_select',
        'body' => 'سؤال',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

it('rejects multi_select with non-existent choice id (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'multi_select',
        'body' => 'سؤال',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['z'],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

// ================================================================ numerical — التأليف

it('creates a numerical question with tolerance', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'numerical',
        'body' => 'كم تساوي؟',
        'correct' => [100],
        'config' => ['tolerance' => 2],
    ])->assertCreated()
        ->assertJsonPath('data.type', 'numerical')
        ->assertJsonPath('data.correct', [100])
        ->assertJsonPath('data.config.tolerance', 2);
});

it('creates a numerical question with zero tolerance', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'numerical',
        'body' => 'قيمة بي تقريباً؟',
        'correct' => [3.14],
        'config' => ['tolerance' => 0],
    ])->assertCreated();
});

it('rejects numerical without numeric correct value (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'numerical',
        'body' => 'سؤال',
        'correct' => ['نصّ'],
        'config' => ['tolerance' => 0],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

it('rejects numerical with negative tolerance (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'numerical',
        'body' => 'سؤال',
        'correct' => [100],
        'config' => ['tolerance' => -1],
    ])->assertStatus(422)->assertJsonValidationErrors('config.tolerance');
});

it('rejects numerical with correct array of two values (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'numerical',
        'body' => 'سؤال',
        'correct' => [100, 200],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

// ================================================================ regex — التأليف

it('creates a regex question with valid pattern', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'regex',
        'body' => 'أدخل ثلاثة أرقام',
        'correct' => ['^\\d{3}$'],
        'config' => ['flags' => ''],
    ])->assertCreated()
        ->assertJsonPath('data.type', 'regex')
        ->assertJsonPath('data.correct', ['^\\d{3}$']);
});

it('creates a regex question with case-insensitive flag', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'regex',
        'body' => 'اكتب yes',
        'correct' => ['^yes$'],
        'config' => ['flags' => 'i'],
    ])->assertCreated()
        ->assertJsonPath('data.config.flags', 'i');
});

it('rejects regex with invalid pattern (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'regex',
        'body' => 'سؤال',
        'correct' => ['('],  // نمط غير صالح
        'config' => ['flags' => ''],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

it('rejects regex pattern longer than 200 characters (422)', function () {
    $longPattern = str_repeat('a', 201);
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'regex',
        'body' => 'سؤال',
        'correct' => [$longPattern],
        'config' => ['flags' => ''],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

it('rejects regex with disallowed flag (422)', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'regex',
        'body' => 'سؤال',
        'correct' => ['^test$'],
        'config' => ['flags' => 'g'],  // علَم غير مسموح
    ])->assertStatus(422)->assertJsonValidationErrors('config.flags');
});

// ================================================================ الأداء — تصحيح E2E

it('grades a full attempt with all four new types correctly', function () {
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $this->course);

    $qDropdown = e4Question($this->course, [
        'type' => 'dropdown',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['a'],
        'config' => null,
    ]);
    $qMulti = e4Question($this->course, [
        'type' => 'multi_select',
        'choices' => [['id' => 'x', 'text' => 'X'], ['id' => 'y', 'text' => 'Y'], ['id' => 'z', 'text' => 'Z']],
        'correct' => ['x', 'z'],
        'config' => null,
    ]);
    $qNum = e4Question($this->course, [
        'type' => 'numerical',
        'choices' => null,
        'correct' => [100],
        'config' => ['tolerance' => 2],
    ]);
    $qReg = e4Question($this->course, [
        'type' => 'regex',
        'choices' => null,
        'correct' => ['^\\d{3}$'],
        'config' => ['flags' => ''],
    ]);

    $quiz = e4Quiz($this->course, [$qDropdown, $qMulti, $qNum, $qReg]);

    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();
    $attemptId = $start->json('attempt.id');

    // كل إجابات صحيحة → score=100
    $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
        'answers' => [
            $qDropdown->id => ['a'],
            $qMulti->id => ['z', 'x'],   // order-independent
            $qNum->id => '101',         // ضمن هامش ±2
            $qReg->id => '456',         // يطابق ^\d{3}$
        ],
    ])->assertOk()
        ->assertJsonPath('data.score', 100)
        ->assertJsonPath('data.passed', true);
});

it('grades all four new types as zero when answers are wrong', function () {
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $this->course);

    $qDropdown = e4Question($this->course, [
        'type' => 'dropdown',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['a'],
        'config' => null,
    ]);
    $qMulti = e4Question($this->course, [
        'type' => 'multi_select',
        'choices' => [['id' => 'x', 'text' => 'X'], ['id' => 'y', 'text' => 'Y'], ['id' => 'z', 'text' => 'Z']],
        'correct' => ['x', 'z'],
        'config' => null,
    ]);
    $qNum = e4Question($this->course, [
        'type' => 'numerical',
        'choices' => null,
        'correct' => [100],
        'config' => ['tolerance' => 2],
    ]);
    $qReg = e4Question($this->course, [
        'type' => 'regex',
        'choices' => null,
        'correct' => ['^\\d{3}$'],
        'config' => ['flags' => ''],
    ]);

    $quiz = e4Quiz($this->course, [$qDropdown, $qMulti, $qNum, $qReg]);

    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();
    $attemptId = $start->json('attempt.id');

    // كل إجابات خاطئة → score=0
    $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
        'answers' => [
            $qDropdown->id => ['b'],       // خطأ
            $qMulti->id => ['x'],       // جزئي — خطأ
            $qNum->id => '200',       // خارج الهامش
            $qReg->id => 'ab',        // لا يطابق ^\d{3}$
        ],
    ])->assertOk()
        ->assertJsonPath('data.score', 0)
        ->assertJsonPath('data.passed', false);
});

// ================================================================ الأمان — حجب correct وconfig عن الطالب

it('QuestionResource does not expose correct for any new type', function () {
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $this->course);

    $qNum = e4Question($this->course, [
        'type' => 'numerical',
        'choices' => null,
        'correct' => [42],
        'config' => ['tolerance' => 1],
    ]);
    $qReg = e4Question($this->course, [
        'type' => 'regex',
        'choices' => null,
        'correct' => ['^test$'],
        'config' => ['flags' => 'i'],
    ]);

    $quiz = e4Quiz($this->course, [$qNum, $qReg]);

    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();

    // الطالب لا يرى correct ولا config في بداية المحاولة
    $content = $start->getContent();
    expect($content)->not->toContain('"correct"')
        ->and($content)->not->toContain('"config"')
        ->and($content)->not->toContain('"tolerance"')
        ->and($content)->not->toContain('"flags"')
        ->and($content)->not->toContain('42')       // القيمة الرقمية
        ->and($content)->not->toContain('^test$');  // النمط
});

it('QuestionResource exposes choices for dropdown and multi_select but not correct', function () {
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $this->course);

    $qDrop = e4Question($this->course, [
        'type' => 'dropdown',
        'choices' => [['id' => 'a', 'text' => 'الرياض'], ['id' => 'b', 'text' => 'جدة']],
        'correct' => ['a'],
        'config' => null,
    ]);

    $quiz = e4Quiz($this->course, [$qDrop]);

    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();

    // الخيارات مكشوفة (للعرض)، correct محجوب
    // نستخدم json() لفكّ ترميز Unicode بدلاً من getContent()
    $data = $start->json();
    $firstQuestion = $data['questions'][0];
    expect($firstQuestion)->toHaveKey('choices')
        ->and($firstQuestion['choices'][0]['text'])->toBe('الرياض')
        ->and($firstQuestion)->not->toHaveKey('correct');
});

// ================================================================ عدم كسر الأنواع القائمة

it('existing MCQ question creation still works after E4', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'mcq',
        'body' => 'ما عاصمة السعودية؟',
        'choices' => [['id' => 'a', 'text' => 'الرياض'], ['id' => 'b', 'text' => 'جدة']],
        'correct' => ['a'],
        'points' => 2,
    ])->assertCreated()->assertJsonPath('data.type', 'mcq');
});

it('existing TrueFalse question creation still works after E4', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'true_false',
        'body' => 'الأرض كروية؟',
        'correct' => true,
    ])->assertCreated()->assertJsonPath('data.type', 'true_false');
});

it('existing ShortAnswer question creation still works after E4', function () {
    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'short_answer',
        'body' => 'ما لغة PHP؟',
        'correct' => ['برمجة', 'لغة برمجة'],
    ])->assertCreated()->assertJsonPath('data.type', 'short_answer');
});

it('existing quiz attempt flow still works after E4 for MCQ', function () {
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $this->course);

    $q = e4Question($this->course, [
        'type' => 'mcq',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['a'],
        'config' => null,
    ]);
    $quiz = e4Quiz($this->course, [$q]);

    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();
    $attemptId = $start->json('attempt.id');

    $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
        'answers' => [$q->id => ['a']],
    ])->assertOk()
        ->assertJsonPath('data.score', 100)
        ->assertJsonPath('data.passed', true);
});

// ================================================================ QuestionAdminResource يكشف config للمؤلّف

it('QuestionAdminResource exposes config for numerical', function () {
    $response = $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'numerical',
        'body' => 'سؤال',
        'correct' => [50],
        'config' => ['tolerance' => 5],
    ])->assertCreated();

    expect($response->json('data.config.tolerance'))->toBe(5)
        ->and($response->json('data.correct'))->toBe([50]);
});

it('QuestionAdminResource exposes config.flags for regex', function () {
    $response = $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'regex',
        'body' => 'سؤال',
        'correct' => ['^yes$'],
        'config' => ['flags' => 'i'],
    ])->assertCreated();

    expect($response->json('data.config.flags'))->toBe('i')
        ->and($response->json('data.correct'))->toBe(['^yes$']);
});

// ================================================================ regex — أمان ReDoS

it('regex grading handles answer at the length boundary safely', function () {
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $this->course);

    $qReg = e4Question($this->course, [
        'type' => 'regex',
        'choices' => null,
        'correct' => ['^.*$'],
        'config' => ['flags' => ''],
    ]);
    $quiz = e4Quiz($this->course, [$qReg]);

    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();
    $attemptId = $start->json('attempt.id');

    // إجابة بطول 2001 محرف — تُعامَل كخطأ بلا 500
    $longAnswer = str_repeat('أ', 2001);
    $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
        'answers' => [$qReg->id => $longAnswer],
    ])->assertOk()
        ->assertJsonPath('data.score', 0);
});

// ================================================================ نقاط أعداد صحيحة

it('points are integers in scoring (binary — كل أو لا شيء)', function () {
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $this->course);

    $q = e4Question($this->course, [
        'type' => 'numerical',
        'choices' => null,
        'correct' => [10],
        'config' => ['tolerance' => 0],
        'points' => 3,
    ]);
    $quiz = e4Quiz($this->course, [$q]);

    Sanctum::actingAs($student);
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();
    $attemptId = $start->json('attempt.id');

    $result = $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
        'answers' => [$q->id => '10'],
    ])->assertOk();

    expect($result->json('data.score'))->toBe(100)
        ->and($result->json('data.score'))->toBeInt();
});
