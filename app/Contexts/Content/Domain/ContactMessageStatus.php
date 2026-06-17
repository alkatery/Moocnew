<?php

declare(strict_types=1);

namespace App\Contexts\Content\Domain;

/**
 * Triage state of a contact-form message: it arrives `new` and is marked
 * `handled` once staff have dealt with it.
 */
enum ContactMessageStatus: string
{
    case New = 'new';
    case Handled = 'handled';
}
