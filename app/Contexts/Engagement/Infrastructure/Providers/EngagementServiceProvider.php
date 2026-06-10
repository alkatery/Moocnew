<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Infrastructure\Providers;

use App\Contexts\Engagement\Application\AwardLearningPoints;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Enrollment\Domain\Events\LessonCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Engagement context: awards gamification points from learning
 * events, keeping it decoupled from the Enrollment context.
 */
final class EngagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(LessonCompleted::class, [AwardLearningPoints::class, 'onLessonCompleted']);
        Event::listen(EnrollmentCompleted::class, [AwardLearningPoints::class, 'onCourseCompleted']);
    }
}
