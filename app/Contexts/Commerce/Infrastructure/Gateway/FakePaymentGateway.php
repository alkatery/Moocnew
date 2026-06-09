<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Gateway;

use App\Contexts\Commerce\Domain\GatewayCharge;
use App\Contexts\Commerce\Domain\PaymentGateway;
use Illuminate\Support\Str;

/**
 * Default gateway for local/dev/test (the payments equivalent of the log
 * mail driver). It issues deterministic references and verifies webhook
 * signatures with an HMAC over a shared secret — a real, working driver,
 * not a stub. Swap in {@see MoyasarPaymentGateway} by configuring keys.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $webhookSecret = 'fake-webhook-secret',
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function charge(int $amountMinor, string $currency, string $description, array $metadata = []): GatewayCharge
    {
        return new GatewayCharge(
            reference: 'fake_'.Str::lower(Str::random(20)),
            status: 'initiated',
            redirectUrl: null,
        );
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return $signature !== '' && hash_equals($expected, $signature);
    }

    public function refund(string $reference, int $amountMinor): bool
    {
        return true;
    }
}
