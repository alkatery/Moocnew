<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Application;

use App\Contexts\Commerce\Domain\Ledger\AccountType;
use App\Contexts\Commerce\Domain\Ledger\PostingLine;
use App\Contexts\Commerce\Domain\PayoutStatus;
use App\Contexts\Commerce\Infrastructure\Persistence\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Instructor earnings withdrawals (PRD §5.د): a request is bounded by the
 * instructor's ledger balance and a configured minimum; settling it posts a
 * balanced ledger transaction (reduce payable, reduce cash).
 */
final class PayoutService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function request(User $instructor, int $amountMinor, string $currency = 'SAR'): PayoutRequest
    {
        $minimum = (int) config('commerce.minimum_payout_minor', 10000);
        if ($amountMinor < $minimum) {
            throw ValidationException::withMessages(['amount' => ["الحد الأدنى للسحب هو {$minimum}."]]);
        }

        $balance = $this->ledger->instructorBalance($instructor->getKey(), $currency);
        if ($amountMinor > $balance->minor) {
            throw ValidationException::withMessages(['amount' => ['المبلغ يتجاوز الرصيد المتاح.']]);
        }

        return PayoutRequest::query()->create([
            'instructor_id' => $instructor->getKey(),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => PayoutStatus::Pending,
        ]);
    }

    public function approve(PayoutRequest $payout, User $admin): PayoutRequest
    {
        $this->assertPending($payout);
        $payout->update(['status' => PayoutStatus::Approved, 'processed_by' => $admin->getKey()]);

        return $payout;
    }

    public function reject(PayoutRequest $payout, User $admin, ?string $note = null): PayoutRequest
    {
        $this->assertPending($payout);
        $payout->update([
            'status' => PayoutStatus::Rejected,
            'processed_by' => $admin->getKey(),
            'note' => $note,
            'processed_at' => Date::now(),
        ]);

        return $payout;
    }

    public function markPaid(PayoutRequest $payout, User $admin): PayoutRequest
    {
        if (! in_array($payout->status, [PayoutStatus::Pending, PayoutStatus::Approved], true)) {
            throw ValidationException::withMessages(['status' => ['لا يمكن دفع هذا الطلب.']]);
        }

        return DB::transaction(function () use ($payout, $admin): PayoutRequest {
            // Re-check balance at settlement time to avoid double payouts.
            $balance = $this->ledger->instructorBalance($payout->instructor_id, $payout->currency);
            if ($payout->amount_minor > $balance->minor) {
                throw ValidationException::withMessages(['amount' => ['الرصيد لم يعد كافياً.']]);
            }

            $this->ledger->post(
                [
                    PostingLine::debit(AccountType::InstructorPayable, $payout->amount_minor, $payout->instructor_id),
                    PostingLine::credit(AccountType::Cash, $payout->amount_minor),
                ],
                $payout->currency,
                'payout',
                $payout->id,
                "Payout #{$payout->id}",
            );

            $payout->update([
                'status' => PayoutStatus::Paid,
                'processed_by' => $admin->getKey(),
                'processed_at' => Date::now(),
            ]);

            return $payout;
        });
    }

    private function assertPending(PayoutRequest $payout): void
    {
        if ($payout->status !== PayoutStatus::Pending) {
            throw ValidationException::withMessages(['status' => ['الطلب ليس قيد الانتظار.']]);
        }
    }
}
