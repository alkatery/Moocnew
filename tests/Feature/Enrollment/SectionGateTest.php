<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Assessment\Infrastructure\Persistence\QuizAttempt;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\LessonAccess;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('locks the next unit until the unit gate quiz is passed', function () {
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->for($instructor, 'instructor')->published()->create();
    $s1 = $course->sections()->create(['title' => 'الوحدة 1', 'position' => 1]);
    $s2 = $course->sections()->create(['title' => 'الوحدة 2', 'position' => 2]);
    $lesson1 = $s1->lessons()->create(['title' => 'درس 1', 'type' => 'article', 'position' => 1]);
    $lesson2 = $s2->lessons()->create(['title' => 'درس 2', 'type' => 'article', 'position' => 1]);
    $gate = Quiz::query()->create([
        'course_id' => $course->id, 'section_id' => $s1->id, 'title' => 'بوّابة الوحدة 1',
        'is_gate' => true, 'pass_mark' => 50, 'position' => 2,
    ]);

    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Active, 'enrolled_at' => now(),
    ]);

    $access = app(LessonAccess::class);
    expect($access->canAccess($student, $lesson1))->toBeTrue();   // الوحدة 1 مفتوحة
    expect($access->canAccess($student, $lesson2))->toBeFalse();  // الوحدة 2 مقفلة

    // اجتياز البوّابة يفتح الوحدة التالية.
    QuizAttempt::query()->create([
        'quiz_id' => $gate->id, 'user_id' => $student->id, 'question_ids' => [],
        'score' => 100, 'passed' => true, 'started_at' => now()->subMinutes(3), 'submitted_at' => now(),
    ]);

    expect($access->canAccess($student, $lesson2))->toBeTrue();
});

it('does not lock units when the section quiz is not a gate', function () {
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->for($instructor, 'instructor')->published()->create();
    $s1 = $course->sections()->create(['title' => 'و1', 'position' => 1]);
    $s2 = $course->sections()->create(['title' => 'و2', 'position' => 2]);
    $lesson2 = $s2->lessons()->create(['title' => 'د2', 'type' => 'article', 'position' => 1]);
    Quiz::query()->create([
        'course_id' => $course->id, 'section_id' => $s1->id, 'title' => 'اختبار عادي',
        'is_gate' => false, 'pass_mark' => 50, 'position' => 1,
    ]);

    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Active, 'enrolled_at' => now(),
    ]);

    expect(app(LessonAccess::class)->canAccess($student, $lesson2))->toBeTrue();
});

it('lets course staff bypass unit gates', function () {
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->for($instructor, 'instructor')->published()->create();
    $s1 = $course->sections()->create(['title' => 'و1', 'position' => 1]);
    $s2 = $course->sections()->create(['title' => 'و2', 'position' => 2]);
    $lesson2 = $s2->lessons()->create(['title' => 'د2', 'type' => 'article', 'position' => 1]);
    Quiz::query()->create([
        'course_id' => $course->id, 'section_id' => $s1->id, 'title' => 'بوّابة',
        'is_gate' => true, 'pass_mark' => 50, 'position' => 1,
    ]);

    expect(app(LessonAccess::class)->canAccess($instructor, $lesson2))->toBeTrue();
});
