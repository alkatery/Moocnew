<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain;

/**
 * The result of initiating a charge at the payment gateway.
 */
final readonly class GatewayCharge
{
    public function __construct(
        public string $reference,
        public string $status,
        public ?string $redirectUrl = null,
    ) {}
}
