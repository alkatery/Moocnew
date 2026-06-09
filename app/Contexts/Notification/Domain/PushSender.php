<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Domain;

/** Contract for sending a push notification (PRD §5.ط). */
interface PushSender
{
    /** @param array<string, mixed> $payload */
    public function send(string $recipient, string $title, array $payload = []): void;
}
