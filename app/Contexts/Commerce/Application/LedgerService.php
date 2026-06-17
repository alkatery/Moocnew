<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Application;

use App\Contexts\Commerce\Domain\Ledger\AccountType;
use App\Contexts\Commerce\Domain\Ledger\PostingLine;
use App\Contexts\Commerce\Infrastructure\Persistence\LedgerAccount;
use App\Contexts\Commerce\Infrastructure\Persistence\LedgerEntry;
use App\Contexts\Shared\Domain\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The double-entry ledger (PRD §5.د). Every financial event is posted as a
 * single balanced transaction whose debits equal its credits; this is the
 * authoritative source for instructor balances, payouts and settlements.
 */
final class LedgerService
{
    /**
     * Post a balanced transaction. Throws if debits != credits.
     *
     * @param  list<PostingLine>  $lines
     */
    public function post(array $lines, string $currency, ?string $refType = null, ?int $refId = null, ?string $memo = null): string
    {
        $totalDebit = array_sum(array_map(fn (PostingLine $l): int => $l->debitMinor, $lines));
        $totalCredit = array_sum(array_map(fn (PostingLine $l): int => $l->creditMinor, $lines));

        if ($totalDebit !== $totalCredit || $totalDebit === 0) {
            throw new InvalidArgumentException('Ledger transaction is not balanced.');
        }

        return DB::transaction(function () use ($lines, $currency, $refType, $refId, $memo): string {
            $uuid = (string) Str::uuid();

            foreach ($lines as $line) {
                $account = $this->accountFor($line->account, $line->ownerId, $currency);

                LedgerEntry::query()->create([
                    'transaction_uuid' => $uuid,
                    'account_id' => $account->id,
                    'debit_minor' => $line->debitMinor,
                    'credit_minor' => $line->creditMinor,
                    'currency' => $currency,
                    'ref_type' => $refType,
                    'ref_id' => $refId,
                    'memo' => $memo,
                ]);
            }

            return $uuid;
        });
    }

    /**
     * Credit-normal balance (credits - debits) for an account — used for
     * revenue and instructor-payable accounts.
     */
    public function balance(AccountType $type, ?int $ownerId, string $currency = 'SAR'): Money
    {
        $account = $this->accountFor($type, $ownerId, $currency);

        $credits = (int) LedgerEntry::query()->where('account_id', $account->id)->sum('credit_minor');
        $debits = (int) LedgerEntry::query()->where('account_id', $account->id)->sum('debit_minor');

        return Money::ofMinor(max(0, $credits - $debits), $currency);
    }

    public function instructorBalance(int $instructorId, string $currency = 'SAR'): Money
    {
        return $this->balance(AccountType::InstructorPayable, $instructorId, $currency);
    }

    private function accountFor(AccountType $type, ?int $ownerId, string $currency): LedgerAccount
    {
        $ownerId = $type->isPerInstructor() ? $ownerId : null;

        return LedgerAccount::query()->firstOrCreate(
            ['type' => $type->value, 'owner_id' => $ownerId, 'currency' => $currency],
        );
    }
}
