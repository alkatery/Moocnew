<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
