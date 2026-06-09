<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Domain;

/** Contract for sending a WhatsApp message (PRD §5.ط). */
interface WhatsAppSender
{
    public function send(string $to, string $message): void;
}
