<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Domain\Events\EnrollmentActivated;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Platform\Application\FeatureFlags;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The single entry point for enrolling a learner (PRD §1, §5.ج). Enrollment
 * is decoupled from payment: in free mode — or for a free course — the
 * learner is enrolled and active immediately. Only when the payments flag
 * is on AND the course is paid does the enrollment start as `pending`,
 * awaiting an order that the (Phase 2) Commerce context will settle.
 */
final class EnrollmentService
{
    public function __construct(
        private readonly FeatureFlags $features,
        private readonly ActivityLogger $activity,
    ) {}

    public function enroll(User $user, Course $course): Enrollment
    {
        return DB::transaction(function () use ($user, $course): Enrollment {
            $existing = Enrollment::query()
                ->where('user_id', $user->getKey())
                ->where('course_id', $course->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $immediateAccess = $this->grantsImmediateAccess($course);

            $enrollment = Enrollment::query()->create([
                'user_id' => $user->getKey(),
                'course_id' => $course->getKey(),
                'status' => $immediateAccess ? EnrollmentStatus::Active : EnrollmentStatus::Pending,
                'enrolled_at' => $immediateAccess ? Date::now() : null,
                'progress_percent' => 0,
            ]);

            $this->activity->log('enrollment.created', $user, $enrollment, [
                'course_id' => $course->getKey(),
                'status' => $enrollment->status->value,
            ]);

            if ($enrollment->status === EnrollmentStatus::Active) {
                Event::dispatch(new EnrollmentActivated(
                    $enrollment->getKey(),
                    $user->getKey(),
                    $course->getKey(),
                ));
            }

            return $enrollment;
        });
    }

    /**
     * Activate (or create) a learner's enrollment once their order is paid,
     * linking it to the order. Idempotent: an already-active enrollment is
     * returned unchanged. Called by the Commerce context on payment success.
     */
    public function activateForOrder(int $userId, int $courseId, int $orderId): Enrollment
    {
        return DB::transaction(function () use ($userId, $courseId, $orderId): Enrollment {
            $enrollment = Enrollment::query()
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->lockForUpdate()
                ->first()
                ?? new Enrollment([
                    'user_id' => $userId,
                    'course_id' => $courseId,
                    'progress_percent' => 0,
                ]);

            if ($enrollment->status === EnrollmentStatus::Active || $enrollment->status === EnrollmentStatus::Completed) {
                return $enrollment;
            }

            $enrollment->status = EnrollmentStatus::Active;
            $enrollment->order_id = $orderId;
            $enrollment->enrolled_at ??= Date::now();
            $enrollment->save();

            Event::dispatch(new EnrollmentActivated($enrollment->getKey(), $userId, $courseId));

            return $enrollment;
        });
    }

    /**
     * Mark a learner's enrollment refunded (revokes access), e.g. when their
     * order is refunded.
     */
    public function refundForOrder(int $orderId): void
    {
        Enrollment::query()
            ->where('order_id', $orderId)
            ->update(['status' => EnrollmentStatus::Refunded->value]);
    }

    /**
     * A course grants access on enrollment when payments are off entirely,
     * or the course is free / zero-priced.
     */
    private function grantsImmediateAccess(Course $course): bool
    {
        if (! $this->features->paymentsEnabled()) {
            return true;
        }

        return $course->pricing_type === PricingType::Free || $course->price_minor === 0;
    }
}
