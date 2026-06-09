<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain;

/**
 * Payment aggregator contract (PRD §5.د, ADR-0004). The application depends
 * only on this; Moyasar is the production implementation and a fake gateway
 * backs automated tests. Webhooks must be signature-verified and idempotent.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Initiate a charge and return its gateway reference.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function charge(int $amountMinor, string $currency, string $description, array $metadata = []): GatewayCharge;

    /**
     * Verify an inbound webhook's signature against the raw request body.
     */
    public function verifySignature(string $payload, string $signature): bool;

    /**
     * Refund a previously captured payment. Returns true on success.
     */
    public function refund(string $reference, int $amountMinor): bool;
}
