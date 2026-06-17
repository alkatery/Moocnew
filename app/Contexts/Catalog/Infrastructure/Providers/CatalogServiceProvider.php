<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Infrastructure\Providers;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Policies\CoursePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Catalog context. The Course model lives outside app/Models, so
 * its policy is registered explicitly rather than by auto-discovery.
 */
final class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Course::class, CoursePolicy::class);
    }
}
