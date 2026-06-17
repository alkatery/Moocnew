<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Domain;

/**
 * The learner-facing state of a course inside a learning path: courses are
 * taken strictly in order, so an item is locked until everything before it
 * is completed.
 */
enum PathItemState: string
{
    case Completed = 'completed';
    case Unlocked = 'unlocked';
    case Locked = 'locked';
}
