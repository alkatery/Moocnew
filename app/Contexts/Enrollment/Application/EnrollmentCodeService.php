<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Domain\Events\EnrollmentActivated;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Enrollment\Infrastructure\Persistence\EnrollmentCode;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Issues and redeems self-enrollment codes. Redemption validates the code
 * (exists, not expired, not exhausted) under a row lock so concurrent
 * redemptions never overshoot max_uses, then enrolls the learner as an
 * active student — codes bypass payment by design (the staff member who
 * issued the code is vouching for the cohort).
 */
final class EnrollmentCodeService
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function issue(User $actor, Course $course, ?int $maxUses, ?string $expiresAt): EnrollmentCode
    {
        $code = EnrollmentCode::query()->create([
            'course_id' => $course->getKey(),
            'code' => EnrollmentCode::generateCode(),
            'max_uses' => $maxUses,
            'used_count' => 0,
            'expires_at' => $expiresAt,
        ]);

        $this->activity->log('enrollment_code.created', $actor, $code, [
            'course_id' => $course->getKey(),
        ]);

        return $code;
    }

    /**
     * Redeem a code for the given learner: validate, enroll active, and
     * increment used_count atomically. Returns the course enrolled into.
     *
     * @throws ValidationException
     */
    public function redeem(User $user, string $code): Course
    {
        return DB::transaction(function () use ($user, $code): Course {
            $enrollmentCode = EnrollmentCode::query()
                ->where('code', mb_strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            if ($enrollmentCode === null) {
                throw ValidationException::withMessages(['code' => 'كود الالتحاق غير صحيح.']);
            }

            if ($enrollmentCode->isExpired()) {
                throw ValidationException::withMessages(['code' => 'انتهت صلاحية هذا الكود.']);
            }

            if ($enrollmentCode->isExhausted()) {
                throw ValidationException::withMessages(['code' => 'تم استنفاد عدد استخدامات هذا الكود.']);
            }

            $course = $enrollmentCode->course;

            $existing = Enrollment::query()
                ->where('user_id', $user->getKey())
                ->where('course_id', $course->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                // Already enrolled: activate a pending enrollment, but never
                // consume a use for a learner who is already in the course.
                if (! $existing->status->grantsAccess()) {
                    $existing->status = EnrollmentStatus::Active;
                    $existing->enrolled_at ??= Date::now();
                    $existing->save();

                    Event::dispatch(new EnrollmentActivated($existing->getKey(), $user->getKey(), $course->getKey()));
                }

                return $course;
            }

            $enrollment = Enrollment::query()->create([
                'user_id' => $user->getKey(),
                'course_id' => $course->getKey(),
                'status' => EnrollmentStatus::Active,
                'enrolled_at' => Date::now(),
                'progress_percent' => 0,
            ]);

            $enrollmentCode->increment('used_count');

            $this->activity->log('enrollment_code.redeemed', $user, $enrollmentCode, [
                'course_id' => $course->getKey(),
            ]);

            Event::dispatch(new EnrollmentActivated($enrollment->getKey(), $user->getKey(), $course->getKey()));

            return $course;
        });
    }
}
