<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Sms;

use App\Contexts\Notification\Domain\WhatsAppSender;
use Psr\Log\LoggerInterface;

/**
 * Default WhatsApp gateway that writes to the log. Swap for a real provider
 * (e.g. WhatsApp Cloud API) by rebinding {@see WhatsAppSender}.
 */
final class LogWhatsAppSender implements WhatsAppSender
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(string $to, string $message): void
    {
        $this->logger->info('WhatsApp dispatched', ['to' => $to, 'message' => $message]);
    }
}
