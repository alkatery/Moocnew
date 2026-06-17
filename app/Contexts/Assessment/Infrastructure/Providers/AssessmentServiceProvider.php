<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Infrastructure\Providers;

use App\Contexts\Assessment\Application\CourseGradeService;
use App\Contexts\Enrollment\Domain\Grading\CourseGradeProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Assessment context: provides the real course-grade computation
 * that Enrollment's completion logic uses to gate certificates on passing
 * the course's assessments (the Edraak model).
 */
final class AssessmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CourseGradeProvider::class, CourseGradeService::class);
    }
}
