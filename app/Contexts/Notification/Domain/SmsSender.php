<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Domain;

/**
 * Contract for sending an SMS (PRD §2: Unifonic/Taqnyat). The application
 * depends only on this; the concrete gateway is swapped via configuration.
 */
interface SmsSender
{
    /**
     * @param  string  $to  recipient phone number in E.164 format
     */
    public function send(string $to, string $message): void;
}
