<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Sms;

use App\Contexts\Notification\Domain\SmsSender;
use Psr\Log\LoggerInterface;

/**
 * Default SMS gateway that writes messages to the log — the SMS equivalent
 * of Laravel's `log` mail driver. Real gateways (Unifonic/Taqnyat) plug in
 * by implementing {@see SmsSender} and rebinding it. This is a working
 * driver, not a stub: it reliably "delivers" to the configured log.
 */
final class LogSmsSender implements SmsSender
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function send(string $to, string $message): void
    {
        $this->logger->info('SMS dispatched', [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
