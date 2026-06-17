<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Application;

use App\Contexts\Commerce\Domain\Ledger\AccountType;
use App\Contexts\Commerce\Domain\Ledger\PostingLine;
use App\Contexts\Commerce\Domain\OrderStatus;
use App\Contexts\Commerce\Domain\PaymentGateway;
use App\Contexts\Commerce\Domain\PaymentStatus;
use App\Contexts\Commerce\Infrastructure\Persistence\Order;
use App\Contexts\Enrollment\Application\EnrollmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refunds a paid order (PRD §5.د): reverses the gateway charge, reverses the
 * ledger split, and revokes the learner's access.
 */
final class RefundService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly LedgerService $ledger,
        private readonly EnrollmentService $enrollments,
    ) {}

    public function refund(Order $order): Order
    {
        if ($order->status !== OrderStatus::Paid) {
            throw ValidationException::withMessages(['order' => ['لا يمكن استرجاع طلب غير مدفوع.']]);
        }

        return DB::transaction(function () use ($order): Order {
            $payment = $order->payments()->where('status', PaymentStatus::Paid->value)->firstOrFail();

            $this->gateway->refund((string) $payment->gateway_ref, $order->total_minor);

            $payment->update(['status' => PaymentStatus::Refunded]);
            $order->update(['status' => OrderStatus::Refunded]);

            $this->reverseRevenueSplit($order);
            $this->enrollments->refundForOrder($order->id);

            return $order->refresh();
        });
    }

    private function reverseRevenueSplit(Order $order): void
    {
        $total = $order->total();
        $commission = $total->percentage((float) config('commerce.commission_percent', 20));
        $instructorShare = $total->subtract($commission);
        $instructorId = (int) $order->course()->value('instructor_id');

        // Mirror image of the sale: claw back revenue & payable, pay out cash.
        $this->ledger->post(
            [
                PostingLine::debit(AccountType::PlatformRevenue, $commission->minor),
                PostingLine::debit(AccountType::InstructorPayable, $instructorShare->minor, $instructorId),
                PostingLine::credit(AccountType::Cash, $total->minor),
            ],
            $order->currency,
            'refund',
            $order->id,
            "Refund of order #{$order->id}",
        );
    }
}
