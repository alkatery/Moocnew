<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Enrollment\Infrastructure\Persistence\EnrollmentCode;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ------------------------------------------------------------- CSV import

it('imports users from a csv, enrolls them, and skips duplicates', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $course = Course::factory()->published()->create();

    User::factory()->create(['email' => 'old@example.com']);

    $csv = "name,email,phone\n"
        ."أحمد محمد,ahmed@example.com,0501234567\n"
        ."سارة علي,sara@example.com,\n"
        ."مكرر,old@example.com,\n"
        ."بلا بريد,not-an-email,\n";

    $response = $this->actingAs($admin)
        ->post('/api/v1/admin/users/import', [
            'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
            'course_id' => $course->id,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('data.skipped', 1)
        ->assertJsonPath('data.enrolled', 2);

    expect($response->json('data.errors'))->toHaveCount(1)
        ->and($response->json('data.errors.0.line'))->toBe(5);

    $ahmed = User::query()->where('email', 'ahmed@example.com')->first();
    expect($ahmed)->not->toBeNull()
        ->and($ahmed->hasRole(Role::Student->value))->toBeTrue()
        ->and($ahmed->phone)->toBe('0501234567');

    $enrollment = Enrollment::query()
        ->where('user_id', $ahmed->id)
        ->where('course_id', $course->id)
        ->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active);
});

it('imports users without a course and skips in-file duplicates', function () {
    $admin = userWithRole(Role::SuperAdmin);

    $csv = "name,email\nطالب,one@example.com\nطالب آخر,one@example.com\n";

    $this->actingAs($admin)
        ->post('/api/v1/admin/users/import', [
            'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.skipped', 1)
        ->assertJsonPath('data.enrolled', 0);
});

it('forbids csv import without users.manage', function () {
    foreach ([Role::Student, Role::Instructor, Role::Supervisor] as $role) {
        $this->actingAs(userWithRole($role))
            ->post('/api/v1/admin/users/import', [
                'file' => UploadedFile::fake()->createWithContent('users.csv', "name,email\nأ,a@a.com\n"),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
});

// ------------------------------------------------------- enrollment codes

it('creates, lists and deletes enrollment codes for a course', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $course = Course::factory()->published()->create();

    $created = $this->actingAs($admin)
        ->postJson("/api/v1/admin/courses/{$course->slug}/enrollment-codes", ['max_uses' => 30])
        ->assertCreated()
        ->assertJsonPath('data.max_uses', 30)
        ->assertJsonPath('data.used_count', 0);

    expect($created->json('data.code'))->toMatch('/^[A-Z0-9]{8}$/');

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/courses/{$course->slug}/enrollment-codes")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin)
        ->deleteJson('/api/v1/admin/enrollment-codes/'.$created->json('data.id'))
        ->assertStatus(204);

    expect(EnrollmentCode::query()->count())->toBe(0);
});

it('forbids enrollment-code management without courses.review', function () {
    $course = Course::factory()->published()->create();
    $code = EnrollmentCode::query()->create([
        'course_id' => $course->id, 'code' => 'AAAA2222',
    ]);

    foreach ([Role::Student, Role::Instructor] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user)
            ->postJson("/api/v1/admin/courses/{$course->slug}/enrollment-codes", [])
            ->assertForbidden();
        $this->actingAs($user)
            ->getJson("/api/v1/admin/courses/{$course->slug}/enrollment-codes")
            ->assertForbidden();
        $this->actingAs($user)
            ->deleteJson("/api/v1/admin/enrollment-codes/{$code->id}")
            ->assertForbidden();
    }
});

it('redeems a valid code and enrolls the learner as active', function () {
    $course = Course::factory()->published()->create();
    $code = EnrollmentCode::query()->create([
        'course_id' => $course->id, 'code' => 'BBBB3333', 'max_uses' => 2,
    ]);

    $student = userWithRole(Role::Student);

    $this->actingAs($student)
        ->postJson('/api/v1/enrollment-codes/redeem', ['code' => 'bbbb3333'])
        ->assertOk()
        ->assertJsonPath('data.slug', $course->slug)
        ->assertJsonPath('data.title', $course->title);

    $enrollment = Enrollment::query()
        ->where('user_id', $student->id)
        ->where('course_id', $course->id)
        ->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($code->fresh()->used_count)->toBe(1);

    // Redeeming again does not double-enroll or consume another use.
    $this->actingAs($student)
        ->postJson('/api/v1/enrollment-codes/redeem', ['code' => 'BBBB3333'])
        ->assertOk();

    expect($code->fresh()->used_count)->toBe(1)
        ->and(Enrollment::query()->where('user_id', $student->id)->count())->toBe(1);
});

it('rejects an expired code', function () {
    $course = Course::factory()->published()->create();
    EnrollmentCode::query()->create([
        'course_id' => $course->id, 'code' => 'CCCC4444', 'expires_at' => now()->subDay(),
    ]);

    $this->actingAs(userWithRole(Role::Student))
        ->postJson('/api/v1/enrollment-codes/redeem', ['code' => 'CCCC4444'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'انتهت صلاحية هذا الكود.');

    expect(Enrollment::query()->count())->toBe(0);
});

it('rejects an exhausted code and an unknown code', function () {
    $course = Course::factory()->published()->create();
    EnrollmentCode::query()->create([
        'course_id' => $course->id, 'code' => 'DDDD5555', 'max_uses' => 1, 'used_count' => 1,
    ]);

    $student = userWithRole(Role::Student);

    $this->actingAs($student)
        ->postJson('/api/v1/enrollment-codes/redeem', ['code' => 'DDDD5555'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'تم استنفاد عدد استخدامات هذا الكود.');

    $this->actingAs($student)
        ->postJson('/api/v1/enrollment-codes/redeem', ['code' => 'ZZZZ9999'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'كود الالتحاق غير صحيح.');

    expect(Enrollment::query()->count())->toBe(0);
});

// ----------------------------------------------------------- course clone

it('clones a course with sections, lessons, quizzes, questions and assignments', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $course = Course::factory()->published()->create(['passing_grade' => 70]);

    $section = $course->sections()->create(['title' => 'القسم الأول', 'position' => 1]);
    $section->lessons()->create(['title' => 'درس مقال', 'type' => 'article', 'content' => 'النص', 'position' => 1]);
    $section->lessons()->create(['title' => 'درس فيديو', 'type' => 'video', 'position' => 2, 'is_free_preview' => true]);

    $question = Question::query()->create([
        'course_id' => $course->id, 'type' => 'true_false', 'body' => 'سؤال', 'choices' => null, 'correct' => [true], 'points' => 2,
    ]);
    $quiz = Quiz::query()->create([
        'course_id' => $course->id, 'section_id' => $section->id, 'title' => 'اختبار', 'pass_mark' => 60, 'weight' => 3,
    ]);
    $quiz->questions()->sync([$question->id => ['position' => 1]]);

    Assignment::query()->create([
        'course_id' => $course->id, 'section_id' => $section->id, 'title' => 'واجب', 'points' => 100, 'weight' => 2,
    ]);

    // Learner data that must NOT be copied.
    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Active, 'progress_percent' => 50, 'enrolled_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/admin/courses/{$course->slug}/clone")
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', "نسخة من {$course->title}")
        ->assertJsonPath('data.published_at', null);

    $copy = Course::query()->find($response->json('data.id'));

    expect($copy->slug)->not->toBe($course->slug)
        ->and($copy->passing_grade)->toBe(70)
        ->and($copy->sections()->count())->toBe(1);

    $copiedSection = $copy->sections()->first();
    expect($copiedSection->lessons()->count())->toBe(2)
        ->and($copiedSection->lessons()->pluck('title')->all())->toBe(['درس مقال', 'درس فيديو']);

    // Question bank copied and re-linked to the copied quiz.
    $copiedQuestion = Question::query()->where('course_id', $copy->id)->first();
    expect($copiedQuestion)->not->toBeNull()
        ->and($copiedQuestion->id)->not->toBe($question->id)
        ->and($copiedQuestion->points)->toBe(2);

    $copiedQuiz = Quiz::query()->where('course_id', $copy->id)->first();
    expect($copiedQuiz)->not->toBeNull()
        ->and($copiedQuiz->weight)->toBe(3)
        ->and($copiedQuiz->section_id)->toBe($copiedSection->id)
        ->and($copiedQuiz->questions()->pluck('question_bank.id')->all())->toBe([$copiedQuestion->id]);

    $copiedAssignment = Assignment::query()->where('course_id', $copy->id)->first();
    expect($copiedAssignment)->not->toBeNull()
        ->and($copiedAssignment->section_id)->toBe($copiedSection->id);

    // No learner data on the copy.
    expect(Enrollment::query()->where('course_id', $copy->id)->count())->toBe(0);
});

it('forbids cloning without courses.review', function () {
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);

    $this->actingAs($instructor)
        ->postJson("/api/v1/admin/courses/{$course->slug}/clone")
        ->assertForbidden();
});

// ------------------------------------------------------------ CSV reports

it('exports the enrollments csv with BOM and correct headers', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $course = Course::factory()->published()->create(['title' => 'دورة التقارير']);
    $student = userWithRole(Role::Student);

    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Active, 'progress_percent' => 40, 'enrolled_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get("/api/v1/admin/reports/enrollments.csv?course_id={$course->id}&status=active")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($content)->toContain('student_name,email,course,status,progress_percent,grade,enrolled_at,completed_at')
        ->and($content)->toContain('دورة التقارير')
        ->and($content)->toContain($student->email);
});

it('exports the courses csv for published courses', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $published = Course::factory()->published()->create(['title' => 'دورة منشورة']);
    Course::factory()->create(['title' => 'مسودة مخفية']);

    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $published->id,
        'status' => EnrollmentStatus::Completed, 'progress_percent' => 100,
        'enrolled_at' => now(), 'completed_at' => now(),
    ]);

    $content = $this->actingAs($admin)
        ->get('/api/v1/admin/reports/courses.csv')
        ->assertOk()
        ->streamedContent();

    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($content)->toContain('title,instructor,enrollments,completed,avg_progress,avg_rating,reviews_count')
        ->and($content)->toContain('دورة منشورة')
        ->and($content)->not->toContain('مسودة مخفية');
});

it('forbids reports without analytics.view', function () {
    foreach ([Role::Student, Role::Instructor] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user)->get('/api/v1/admin/reports/enrollments.csv')->assertForbidden();
        $this->actingAs($user)->get('/api/v1/admin/reports/courses.csv')->assertForbidden();
    }
});
