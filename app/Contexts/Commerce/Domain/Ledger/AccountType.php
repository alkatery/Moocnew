<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain\Ledger;

/**
 * Chart of accounts for the double-entry ledger (PRD §5.د). The ledger is
 * the backbone of instructor earnings, payouts and settlements.
 *
 * Normal balances: cash & receivables are debit-normal; revenue and
 * payables (what we owe instructors) are credit-normal.
 */
enum AccountType: string
{
    case Cash = 'cash';                       // money received from learners
    case PlatformRevenue = 'platform_revenue'; // our commission
    case InstructorPayable = 'instructor_payable'; // owed to an instructor (per-instructor)
    case Refunds = 'refunds';                 // refunds paid back

    public function isPerInstructor(): bool
    {
        return $this === self::InstructorPayable;
    }
}
