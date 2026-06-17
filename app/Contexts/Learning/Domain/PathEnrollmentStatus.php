<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Domain;

enum PathEnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
}
