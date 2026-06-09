<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain\Ledger;

/**
 * One line of a ledger transaction: a debit or a credit (never both) to an
 * account. Per-instructor accounts carry an owner id.
 */
final readonly class PostingLine
{
    public function __construct(
        public AccountType $account,
        public int $debitMinor,
        public int $creditMinor,
        public ?int $ownerId = null,
    ) {}

    public static function debit(AccountType $account, int $minor, ?int $ownerId = null): self
    {
        return new self($account, $minor, 0, $ownerId);
    }

    public static function credit(AccountType $account, int $minor, ?int $ownerId = null): self
    {
        return new self($account, 0, $minor, $ownerId);
    }
}
