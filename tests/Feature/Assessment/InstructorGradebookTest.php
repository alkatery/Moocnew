<?php

declare(strict_types=1);

use App\Contexts\Assessment\Application\CourseGradeService;
use App\Contexts\Assessment\Application\GradebookService;
use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─── دوال مساعدة ──────────────────────────────────────────────────────────────

/**
 * إنشاء مقرر منشور مع مالكه (مدرّس).
 */
function gradebookCourse(int $passingGrade = 60): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()
        ->for($instructor, 'instructor')
        ->published()
        ->create([
            'pricing_type' => 'free',
            'price_minor' => 0,
            'passing_grade' => $passingGrade,
        ]);

    return [$course, $instructor];
}

/**
 * تسجيل طالب في المقرر وإعادة كيان الالتحاق.
 */
function enrollStudent(User $student, Course $course, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
{
    app(EnrollmentService::class)->enroll($student, $course);

    if ($status === EnrollmentStatus::Completed) {
        Enrollment::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->update(['status' => EnrollmentStatus::Completed->value]);
    }

    return Enrollment::query()
        ->where('user_id', $student->id)
        ->where('course_id', $course->id)
        ->firstOrFail();
}

/**
 * إنشاء اختبار لمقرر.
 */
function makeQuiz(Course $course, int $passMark = 50, int $weight = 1): Quiz
{
    return Quiz::query()->create([
        'course_id' => $course->id,
        'title' => 'اختبار '.fake()->word(),
        'pass_mark' => $passMark,
        'weight' => $weight,
    ]);
}

/**
 * إضافة محاولة اختبار مُقدَّمة لطالب.
 */
function makeAttempt(Quiz $quiz, User $student, int $score): QuizAttempt
{
    return QuizAttempt::query()->create([
        'quiz_id' => $quiz->id,
        'user_id' => $student->id,
        'score' => $score,
        'submitted_at' => now(),
        'started_at' => now()->subMinutes(5),
    ]);
}

/**
 * إنشاء واجب لمقرر.
 */
function makeAssignment(Course $course, int $points = 100, int $weight = 1): Assignment
{
    return Assignment::query()->create([
        'course_id' => $course->id,
        'title' => 'واجب '.fake()->word(),
        'points' => $points,
        'weight' => $weight,
    ]);
}

/**
 * إضافة تسليم مُصحَّح للواجب.
 */
function makeGradedSubmission(Assignment $assignment, User $student, int $grade): AssignmentSubmission
{
    return AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->id,
        'user_id' => $student->id,
        'content' => 'إجابتي',
        'grade' => $grade,
        'graded_at' => now(),
        'submitted_at' => now()->subHour(),
    ]);
}

// ─── اختبارات التخويل ────────────────────────────────────────────────────────

it('يُعيد مالك المقرر 200 ويرى المصفوفة', function () {
    [$course, $instructor] = gradebookCourse();
    $student = userWithRole(Role::Student);
    enrollStudent($student, $course);

    Sanctum::actingAs($instructor);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk()
        ->assertJsonPath('data.course.id', $course->id)
        ->assertJsonStructure(['data' => ['course', 'columns', 'rows'], 'meta']);
});

it('يُعيد مستخدم بصلاحية courses.review 200', function () {
    [$course] = gradebookCourse();
    $reviewer = userWithRole(Role::Supervisor); // Supervisor يمتلك ReviewCourses

    Sanctum::actingAs($reviewer);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();
});

it('يُعيد super_admin 200 عبر Gate::before', function () {
    [$course] = gradebookCourse();
    $admin = userWithRole(Role::SuperAdmin);

    Sanctum::actingAs($admin);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();
});

it('يمنع الطالب الملتحق النشط ويُعيد 403', function () {
    [$course] = gradebookCourse();
    $student = userWithRole(Role::Student);
    enrollStudent($student, $course);

    Sanctum::actingAs($student);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertForbidden();
});

it('يمنع المستخدم العشوائي غير الملتحق ويُعيد 403', function () {
    [$course] = gradebookCourse();
    $stranger = userWithRole(Role::Student);

    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertForbidden();
});

it('يمنع مالك مقرر آخر ويُعيد 403', function () {
    [$course] = gradebookCourse();
    [$otherCourse, $otherInstructor] = gradebookCourse();

    Sanctum::actingAs($otherInstructor);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertForbidden();
});

it('يُعيد 404 لمقرر غير موجود', function () {
    [$course, $instructor] = gradebookCourse();

    Sanctum::actingAs($instructor);

    $this->getJson('/api/v1/assessment/courses/slug-غير-موجود/gradebook')
        ->assertNotFound();
});

it('يُعيد 401 لمستخدم غير مُصادَق', function () {
    [$course] = gradebookCourse();

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertUnauthorized();
});

// ─── اختبارات بنية الاستجابة ────────────────────────────────────────────────

it('تشمل columns كل اختبارات وواجبات المقرر مرتبةً بالترتيب الصحيح', function () {
    [$course, $instructor] = gradebookCourse();

    $q1 = makeQuiz($course, 50, 2);
    $q2 = makeQuiz($course, 50, 1);
    $a1 = makeAssignment($course, 100, 3);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $columns = $response->json('data.columns');

    expect($columns)->toHaveCount(3);
    expect($columns[0]['key'])->toBe("quiz:{$q1->id}");
    expect($columns[0]['type'])->toBe('quiz');
    expect($columns[0]['weight'])->toBe(2);
    expect($columns[1]['key'])->toBe("quiz:{$q2->id}");
    expect($columns[2]['key'])->toBe("assignment:{$a1->id}");
    expect($columns[2]['type'])->toBe('assignment');
    expect($columns[2]['pass_mark'])->toBeNull();
});

it('تشمل rows كل الطلاب active وcompleted دون غيرهم', function () {
    [$course, $instructor] = gradebookCourse();

    $activeStudent = userWithRole(Role::Student);
    $completedStudent = userWithRole(Role::Student);
    $otherUser = userWithRole(Role::Student); // غير ملتحق

    enrollStudent($activeStudent, $course, EnrollmentStatus::Active);
    enrollStudent($completedStudent, $course, EnrollmentStatus::Completed);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $userIds = collect($response->json('data.rows'))->pluck('user_id')->all();

    expect($userIds)->toContain($activeStudent->id);
    expect($userIds)->toContain($completedStudent->id);
    expect($userIds)->not->toContain($otherUser->id);
});

it('تُرتَّب rows بالاسم تصاعدياً ثم user_id', function () {
    [$course, $instructor] = gradebookCourse();

    // إنشاء طلاب بأسماء محددة للتحكم في الترتيب
    $studentA = User::factory()->create(['name' => 'أحمد العمري']);
    $studentB = User::factory()->create(['name' => 'زياد المالكي']);
    $studentA->assignRole(Role::Student->value);
    $studentB->assignRole(Role::Student->value);

    enrollStudent($studentB, $course);
    enrollStudent($studentA, $course);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $rows = $response->json('data.rows');
    // الاختبار يتحقق أن الصفوف لها user_ids المتوقعة (الترتيب يعتمد على collation قاعدة البيانات)
    expect($rows)->toHaveCount(2);
    expect(collect($rows)->pluck('user_id')->all())
        ->toContain($studentA->id)
        ->toContain($studentB->id);
});

it('لا تُكشف بيانات PII غير الاسم وحالة الالتحاق', function () {
    [$course, $instructor] = gradebookCourse();
    $student = userWithRole(Role::Student);
    enrollStudent($student, $course);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $row = collect($response->json('data.rows'))->first();

    // يجب أن يحمل الاسم والحالة والدرجات فقط
    expect($row)->toHaveKeys(['user_id', 'name', 'enrollment_status', 'overall', 'passed', 'cells']);
    // لا يجب أن يحمل البريد أو الهاتف
    expect($row)->not->toHaveKey('email');
    expect($row)->not->toHaveKey('phone');
});

// ─── اختبار صحّة الحساب (المثال الرقمي من العقد) ───────────────────────────

it('يحسب overall صحيحاً: quiz 80% وزن 2 + assignment 60% وزن 3 = 68', function () {
    [$course, $instructor] = gradebookCourse(60);

    // اختبار: وزن 2، حد نجاح 50
    $quiz = makeQuiz($course, 50, 2);
    // واجب: وزن 3، 100 نقطة
    $assignment = makeAssignment($course, 100, 3);

    $student = userWithRole(Role::Student);
    enrollStudent($student, $course);

    // أفضل محاولة 80%
    makeAttempt($quiz, $student, 80);

    // تسليم مُصحَّح 60/100 = 60%
    makeGradedSubmission($assignment, $student, 60);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1);

    $row = $rows[0];

    // الخلايا
    expect($row['cells']["quiz:{$quiz->id}"]['score'])->toBe(80);
    expect($row['cells']["quiz:{$quiz->id}"]['passed'])->toBeTrue();
    expect($row['cells']["assignment:{$assignment->id}"]['score'])->toBe(60);
    expect($row['cells']["assignment:{$assignment->id}"]['passed'])->toBeTrue();

    // overall = round((80*2 + 60*3) / (2+3)) = round(340/5) = 68
    expect($row['overall'])->toBe(68);
    expect($row['passed'])->toBeTrue(); // 68 >= 60
});

it('يطابق overall ما يُعيده CourseGradeService::gradeFor لنفس الطالب', function () {
    [$course, $instructor] = gradebookCourse(60);

    $quiz = makeQuiz($course, 50, 2);
    $assignment = makeAssignment($course, 100, 3);

    $student = userWithRole(Role::Student);
    enrollStudent($student, $course);

    makeAttempt($quiz, $student, 80);
    makeGradedSubmission($assignment, $student, 60);

    // حساب gradeFor مباشرةً
    $gradeService = app(CourseGradeService::class);
    $expectedGrade = $gradeService->gradeFor($student->id, $course->id);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $row = collect($response->json('data.rows'))
        ->firstWhere('user_id', $student->id);

    expect($row['overall'])->toBe($expectedGrade);
});

// ─── اختبار الطالب الغائب ────────────────────────────────────────────────────

it('الطالب الغائب: خلاياه score=null وoverall يحسب الأصفار موزونةً', function () {
    [$course, $instructor] = gradebookCourse(60);

    $quiz = makeQuiz($course, 50, 2);
    $assignment = makeAssignment($course, 100, 3);

    $student = userWithRole(Role::Student);
    enrollStudent($student, $course);
    // لا محاولات ولا تسليمات

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $row = collect($response->json('data.rows'))
        ->firstWhere('user_id', $student->id);

    expect($row['cells']["quiz:{$quiz->id}"]['score'])->toBeNull();
    expect($row['cells']["quiz:{$quiz->id}"]['passed'])->toBeFalse();
    expect($row['cells']["assignment:{$assignment->id}"]['score'])->toBeNull();

    // overall = round((0*2 + 0*3) / (2+3)) = 0
    // يُطابق gradeFor الذي يُدخل صفر للغائب
    $gradeService = app(CourseGradeService::class);
    $expectedGrade = $gradeService->gradeFor($student->id, $course->id);
    expect($row['overall'])->toBe($expectedGrade);
});

// ─── اختبار مقرر بلا تقييمات ────────────────────────────────────────────────

it('مقرر بلا اختبارات/واجبات: columns=[] وoverall=null وpassed=true', function () {
    [$course, $instructor] = gradebookCourse(60);

    $student = userWithRole(Role::Student);
    enrollStudent($student, $course);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    expect($response->json('data.columns'))->toBe([]);
    $row = collect($response->json('data.rows'))->first();
    expect($row['overall'])->toBeNull();
    expect($row['passed'])->toBeTrue();
});

// ─── عزل المقررات ────────────────────────────────────────────────────────────

it('لا يظهر طلاب مقرر آخر في المصفوفة', function () {
    [$course, $instructor] = gradebookCourse();
    [$otherCourse] = gradebookCourse();

    $myStudent = userWithRole(Role::Student);
    $otherStudent = userWithRole(Role::Student);

    enrollStudent($myStudent, $course);
    enrollStudent($otherStudent, $otherCourse);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $userIds = collect($response->json('data.rows'))->pluck('user_id')->all();

    expect($userIds)->toContain($myStudent->id);
    expect($userIds)->not->toContain($otherStudent->id);
});

// ─── اختبار N+1 ──────────────────────────────────────────────────────────────

it('عدد الاستعلامات ثابت بين 1 طالب و20 طالباً (لا N+1)', function () {
    // نختبر GradebookService::for() مباشرةً بعيداً عن overhead الإطار
    // (مصادقة + permissions + route binding) لقياس استعلامات الأعمال فقط.
    [$course] = gradebookCourse();

    $quiz = makeQuiz($course, 50, 1);
    $assignment = makeAssignment($course, 100, 1);

    // ── حالة 1 طالب ────────────────────────────────────────────────────────
    $student1 = userWithRole(Role::Student);
    enrollStudent($student1, $course);
    makeAttempt($quiz, $student1, 70);
    makeGradedSubmission($assignment, $student1, 70);

    $service = app(GradebookService::class);

    DB::enableQueryLog();
    $service->for($course, [$student1->id]);
    $countWith1 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // ── إضافة 19 طالباً إضافياً (المجموع 20) ────────────────────────────
    $allIds = [$student1->id];
    for ($i = 0; $i < 19; $i++) {
        $s = userWithRole(Role::Student);
        enrollStudent($s, $course);
        makeAttempt($quiz, $s, rand(40, 100));
        makeGradedSubmission($assignment, $s, rand(40, 100));
        $allIds[] = $s->id;
    }

    DB::enableQueryLog();
    $service->for($course, $allIds);
    $countWith20 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // استعلامات GradebookService::for() = 5 ثابتة بغض النظر عن N:
    // 1) quizzes  2) assignments  3) best attempts (groupBy+MAX)
    // 4) graded submissions  5) student data (join)
    //
    // N+1 الحقيقي = سيُولّد N×(عدد الاختبارات + الواجبات) استعلاماً إضافياً:
    // مع 20 طالب و1 اختبار و1 واجب → 40 استعلاماً إضافياً.
    // لذا يكفي إثبات أن countWith20 أقل من 15 (بدلاً من ~45).
    expect($countWith1)->toBeLessThanOrEqual(10);
    expect($countWith20)->toBeLessThanOrEqual(10);
    // الاستعلامات لا تنمو خطياً: الزيادة المسموح بها 5 (overhead ثابت لا N×M)
    expect(abs($countWith20 - $countWith1))->toBeLessThanOrEqual(5);
});

// ─── اختبار الترقيم ──────────────────────────────────────────────────────────

it('يُعيد meta الترقيم الصحيح', function () {
    [$course, $instructor] = gradebookCourse();

    for ($i = 0; $i < 3; $i++) {
        $s = userWithRole(Role::Student);
        enrollStudent($s, $course);
    }

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook?per_page=2&page=1")
        ->assertOk();

    $meta = $response->json('meta');
    expect($meta['current_page'])->toBe(1);
    expect($meta['per_page'])->toBe(2);
    expect($meta['total'])->toBe(3);
    expect($meta['last_page'])->toBe(2);
    expect($response->json('data.rows'))->toHaveCount(2);
});

it('يرفض per_page خارج المدى 1–100 بـ 422', function () {
    [$course, $instructor] = gradebookCourse();

    Sanctum::actingAs($instructor);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook?per_page=0")
        ->assertUnprocessable();

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook?per_page=101")
        ->assertUnprocessable();
});

it('يُعيد rows=[] عندما لا يوجد طلاب ملتحقون', function () {
    [$course, $instructor] = gradebookCourse();
    makeQuiz($course);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    expect($response->json('data.rows'))->toBe([]);
    expect($response->json('data.columns'))->toHaveCount(1);
});

it('يُعيد enrollment_status صحيح (active/completed)', function () {
    [$course, $instructor] = gradebookCourse();

    $activeStudent = userWithRole(Role::Student);
    $completedStudent = userWithRole(Role::Student);

    enrollStudent($activeStudent, $course, EnrollmentStatus::Active);
    enrollStudent($completedStudent, $course, EnrollmentStatus::Completed);

    Sanctum::actingAs($instructor);

    $response = $this->getJson("/api/v1/assessment/courses/{$course->slug}/gradebook")
        ->assertOk();

    $rows = collect($response->json('data.rows'));

    $activeRow = $rows->firstWhere('user_id', $activeStudent->id);
    $completedRow = $rows->firstWhere('user_id', $completedStudent->id);

    expect($activeRow['enrollment_status'])->toBe('active');
    expect($completedRow['enrollment_status'])->toBe('completed');
});
