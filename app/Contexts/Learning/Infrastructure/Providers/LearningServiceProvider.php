<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Providers;

use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Learning\Application\SyncLearningProgress;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class LearningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(EnrollmentCompleted::class, SyncLearningProgress::class);
    }
}
