<?php

declare(strict_types=1);

/**
 * اختبارات C2 — مراجعة/تصحيح التسليمات (§6 من العقد)
 *
 * يغطّي:
 *   §6.1 — عرض التسليمات (200 للطاقم، 403 لغيره)
 *   §6.2 — اسم الطالب مكشوف، لا بريد/هاتف (PDPL)
 *   §6.3 — تنزيل الملف (200 للطاقم، 403 لغيره، 404 بلا ملف)
 */

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('media');

    $this->instructor = userWithRole(Role::Instructor);
    $this->course = Course::factory()->published()->for($this->instructor, 'instructor')->create();
    $this->assignment = Assignment::query()->create([
        'course_id' => $this->course->id,
        'title' => 'واجب الاختبار',
        'points' => 100,
    ]);

    // متعلّم ملتحق يسلّم الواجب
    $this->learner = User::factory()->create(['name' => 'سارة المالكي']);
    app(EnrollmentService::class)->enroll($this->learner, $this->course);

    Sanctum::actingAs($this->learner);
    $response = $this->postJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions", [
        'content' => 'هذه إجابتي الكاملة.',
        'file' => UploadedFile::fake()->create('answer.pdf', 50, 'application/pdf'),
    ])->assertCreated();

    $this->submissionId = $response->json('data.id');
    $this->submission = AssignmentSubmission::find($this->submissionId);
});

// ─── §6.1 عرض قائمة التسليمات ────────────────────────────────────────────────

it('يُرجع قائمة التسليمات لطاقم المقرر مع ترقيم', function () {
    Sanctum::actingAs($this->instructor);

    $this->getJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'assignment_id', 'user_id', 'student', 'content', 'has_file', 'file_url', 'grade', 'submitted_at', 'graded_at']],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);
});

it('يمنع مستخدماً عشوائياً من رؤية التسليمات (403)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions")
        ->assertForbidden();
});

it('يمنع المتعلّم الملتحق من رؤية تسليمات الآخرين (403)', function () {
    Sanctum::actingAs($this->learner);

    $this->getJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions")
        ->assertForbidden();
});

// ─── §6.2 اسم الطالب (PDPL) ─────────────────────────────────────────────────

it('يكشف student.name في استجابة index ولا يكشف البريد أو الهاتف', function () {
    Sanctum::actingAs($this->instructor);

    $data = $this->getJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions")
        ->assertOk()
        ->json('data.0');

    // الاسم مكشوف
    expect($data['student']['name'])->toBe('سارة المالكي');
    expect($data['student']['id'])->toBe($this->learner->id);

    // لا بريد ولا هاتف (PDPL)
    expect($data['student'])->not->toHaveKey('email');
    expect($data['student'])->not->toHaveKey('phone');
    expect($data)->not->toHaveKey('email');
    expect($data)->not->toHaveKey('phone');
});

it('يكشف student.name في استجابة grade ولا يكشف البريد أو الهاتف', function () {
    Sanctum::actingAs($this->instructor);

    $data = $this->postJson("/api/v1/assessment/submissions/{$this->submissionId}/grade", [
        'grade' => 90,
        'feedback' => 'ممتاز',
    ])->assertOk()->json('data');

    expect($data['student']['name'])->toBe('سارة المالكي');
    expect($data['student'])->not->toHaveKey('email');
    expect($data['student'])->not->toHaveKey('phone');
});

// ─── §6.3 تنزيل ملف التسليم ──────────────────────────────────────────────────

it('يُرجع ملف التسليم لطاقم المقرر (200)', function () {
    Sanctum::actingAs($this->instructor);

    $this->get("/api/v1/assessment/submissions/{$this->submissionId}/file")
        ->assertOk();
});

it('يمنع مستخدماً عشوائياً من تنزيل الملف (403)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->get("/api/v1/assessment/submissions/{$this->submissionId}/file")
        ->assertForbidden();
});

it('يمنع المتعلّم من تنزيل ملف تسليمه عبر مسار المعلّم (403)', function () {
    Sanctum::actingAs($this->learner);

    $this->get("/api/v1/assessment/submissions/{$this->submissionId}/file")
        ->assertForbidden();
});

it('يُرجع 404 لتسليم بلا ملف مرفق', function () {
    // تسليم نصّي بلا ملف
    $learner2 = User::factory()->create(['name' => 'خالد العمري']);
    app(EnrollmentService::class)->enroll($learner2, $this->course);

    Sanctum::actingAs($learner2);
    $noFileId = $this->postJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions", [
        'content' => 'إجابة نصية فقط بلا ملف.',
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs($this->instructor);
    $this->get("/api/v1/assessment/submissions/{$noFileId}/file")
        ->assertNotFound();
});

it('يكشف file_url صحيحاً في index عند وجود ملف و null عند غيابه', function () {
    // تسليم بلا ملف
    $learner3 = User::factory()->create(['name' => 'نورة الزهراني']);
    app(EnrollmentService::class)->enroll($learner3, $this->course);
    Sanctum::actingAs($learner3);
    $this->postJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions", [
        'content' => 'إجابة بلا ملف.',
    ])->assertCreated();

    Sanctum::actingAs($this->instructor);
    $items = $this->getJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions")
        ->assertOk()
        ->json('data');

    // التسليم الأول (له ملف) يحتوي file_url
    $withFile = collect($items)->firstWhere('user_id', $this->learner->id);
    expect($withFile['has_file'])->toBeTrue();
    expect($withFile['file_url'])->toContain("/api/v1/assessment/submissions/{$this->submissionId}/file");

    // التسليم الثاني (بلا ملف) file_url = null
    $withoutFile = collect($items)->firstWhere('user_id', $learner3->id);
    expect($withoutFile['has_file'])->toBeFalse();
    expect($withoutFile['file_url'])->toBeNull();
});

// ─── مدير المقرر الآخر (طاقم مقرر آخر لا يرى هذه التسليمات) ────────────────

it('يمنع معلّم مقرر آخر من رؤية تسليمات هذا المقرر (403)', function () {
    $otherInstructor = userWithRole(Role::Instructor);
    // لا علاقة له بـ $this->course

    Sanctum::actingAs($otherInstructor);
    $this->getJson("/api/v1/assessment/assignments/{$this->assignment->id}/submissions")
        ->assertForbidden();
});
