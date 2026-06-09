<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Sms;

use App\Contexts\Notification\Domain\PushSender;
use Psr\Log\LoggerInterface;

/**
 * Default push gateway that writes to the log. Swap for FCM/APNs by
 * rebinding {@see PushSender} once device-token registration is added.
 */
final class LogPushSender implements PushSender
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(string $recipient, string $title, array $payload = []): void
    {
        $this->logger->info('Push dispatched', ['to' => $recipient, 'title' => $title, 'payload' => $payload]);
    }
}
