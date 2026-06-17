<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Gateway;

use App\Contexts\Commerce\Domain\GatewayCharge;
use App\Contexts\Commerce\Domain\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Moyasar implementation (mada / Apple Pay / STC Pay), used when API keys
 * are configured. Webhook authenticity is verified with an HMAC-SHA256 over
 * the raw body using the configured webhook secret (PRD §8). Live calls
 * require production/sandbox keys; automated tests drive it via Http::fake.
 */
final class MoyasarPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly string $baseUrl = 'https://api.moyasar.com/v1',
    ) {}

    public function name(): string
    {
        return 'moyasar';
    }

    public function charge(int $amountMinor, string $currency, string $description, array $metadata = []): GatewayCharge
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->post("{$this->baseUrl}/payments", [
                'amount' => $amountMinor,
                'currency' => $currency,
                'description' => $description,
                'metadata' => $metadata,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Moyasar charge failed: '.$response->status());
        }

        return new GatewayCharge(
            reference: (string) $response->json('id'),
            status: (string) $response->json('status', 'initiated'),
            redirectUrl: $response->json('source.transaction_url'),
        );
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return $signature !== '' && hash_equals($expected, $signature);
    }

    public function refund(string $reference, int $amountMinor): bool
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->post("{$this->baseUrl}/payments/{$reference}/refund", ['amount' => $amountMinor]);

        return $response->successful();
    }
}
