<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Application;

use App\Contexts\Commerce\Domain\Ledger\AccountType;
use App\Contexts\Commerce\Domain\Ledger\PostingLine;
use App\Contexts\Commerce\Domain\OrderStatus;
use App\Contexts\Commerce\Domain\PaymentStatus;
use App\Contexts\Commerce\Infrastructure\Persistence\Order;
use App\Contexts\Commerce\Infrastructure\Persistence\Payment;
use App\Contexts\Enrollment\Application\EnrollmentService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Settles a successful payment (PRD §5.د): marks the order paid, posts the
 * revenue split to the double-entry ledger (platform commission +
 * instructor payable), and activates the learner's enrollment. Idempotent —
 * a payment already settled is a no-op.
 */
final class PaymentProcessor
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly EnrollmentService $enrollments,
    ) {}

    public function markPaid(Payment $payment): Order
    {
        return DB::transaction(function () use ($payment): Order {
            /** @var Order $order */
            $order = $payment->order()->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::Paid) {
                return $order;
            }

            $payment->update(['status' => PaymentStatus::Paid]);
            $order->update(['status' => OrderStatus::Paid, 'paid_at' => Date::now()]);

            $this->postRevenueSplit($order);

            $this->enrollments->activateForOrder($order->user_id, $order->course_id, $order->id);

            return $order->refresh();
        });
    }

    private function postRevenueSplit(Order $order): void
    {
        $total = $order->total();
        $commission = $total->percentage((float) config('commerce.commission_percent', 20));
        $instructorShare = $total->subtract($commission);

        $instructorId = (int) $order->course()->value('instructor_id');

        $this->ledger->post(
            [
                PostingLine::debit(AccountType::Cash, $total->minor),
                PostingLine::credit(AccountType::PlatformRevenue, $commission->minor),
                PostingLine::credit(AccountType::InstructorPayable, $instructorShare->minor, $instructorId),
            ],
            $order->currency,
            'order',
            $order->id,
            "Sale of order #{$order->id}",
        );
    }
}
