<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Domain\Events\EnrollmentActivated;
use App\Contexts\Enrollment\Domain\PrerequisitesNotMet;
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

    /**
     * @param  bool  $bypassPrerequisites  true للطاقم (مالك/مراجع/أدمن) — يتجاوز فحص المتطلّبات.
     *                                     يُحدَّد في المتحكّم عبر CourseAccess::isStaffFor.
     *
     * @throws PrerequisitesNotMet إن وُجد متطلّب لم يُكمله المتعلّم.
     */
    public function enroll(User $user, Course $course, bool $bypassPrerequisites = false): Enrollment
    {
        return DB::transaction(function () use ($user, $course, $bypassPrerequisites): Enrollment {
            $existing = Enrollment::query()
                ->where('user_id', $user->getKey())
                ->where('course_id', $course->getKey())
                ->lockForUpdate()
                ->first();

            // الالتحاق القائم (أي حالة) يُعاد كما هو — idempotent، لا فحص للمتطلّبات.
            if ($existing !== null) {
                return $existing;
            }

            // --- فحص المتطلّبات السابقة (E1) ---
            // يأتي قبل grantsImmediateAccess لأنه يحجب الإنشاء بصرف النظر عن الدفع.
            if (! $bypassPrerequisites) {
                $this->assertPrerequisitesMet($user, $course);
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
     * يفحص أن المتعلّم أكمل جميع المتطلّبات السابقة المنشورة للمقرر.
     * «مكتمل» = enrollment.status === Completed (يشمل اجتياز درجة النجاح بحكم CourseCompletionService).
     * استعلامان فقط — لا حلقة N+1.
     *
     * @throws PrerequisitesNotMet
     */
    private function assertPrerequisitesMet(User $user, Course $course): void
    {
        // استعلام 1: اجلب معرّفات المتطلّبات المنشورة للمقرر.
        /** @var list<int> $requiredIds */
        $requiredIds = $course
            ->prerequisites()
            ->where('status', CourseStatus::Published->value)
            ->pluck('prerequisite_course_id')
            ->all();

        if ($requiredIds === []) {
            return;
        }

        // استعلام 2: اجلب المعرّفات التي أكملها المتعلّم فعلاً من تلك المجموعة.
        /** @var list<int> $completedIds */
        $completedIds = Enrollment::query()
            ->where('user_id', $user->getKey())
            ->whereIn('course_id', $requiredIds)
            ->where('status', EnrollmentStatus::Completed->value)
            ->pluck('course_id')
            ->all();

        $missingIds = array_values(array_diff($requiredIds, $completedIds));

        if ($missingIds !== []) {
            throw new PrerequisitesNotMet($missingIds);
        }
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
