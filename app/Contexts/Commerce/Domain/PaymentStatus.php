<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain;

enum PaymentStatus: string
{
    case Initiated = 'initiated';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
