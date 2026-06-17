<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Providers;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Enrollment\Domain\Grading\CourseGradeProvider;
use App\Contexts\Enrollment\Domain\Grading\NullCourseGradeProvider;
use App\Contexts\Enrollment\Infrastructure\Video\BunnyVideoSource;
use App\Contexts\Enrollment\Infrastructure\Video\StorageVideoSource;
use App\Contexts\Enrollment\Infrastructure\Video\VideoSourceResolver;
use App\Contexts\Enrollment\Infrastructure\Video\YouTubeVideoSource;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Enrollment context: registers the per-provider video sources
 * behind a single resolver so playback is provider-agnostic, plus a default
 * (null) course-grade provider that the Assessment context overrides.
 */
final class EnrollmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default: treat courses as having no graded assessments. The
        // Assessment context binds the real implementation over this.
        $this->app->bindIf(CourseGradeProvider::class, NullCourseGradeProvider::class);

        $this->app->singleton(VideoSourceResolver::class, function (): VideoSourceResolver {
            $ttl = (int) config('video.signed_ttl', 7200);

            return new VideoSourceResolver([
                VideoProvider::Bunny->value => new BunnyVideoSource((array) config('video.bunny'), $ttl),
                VideoProvider::Storage->value => new StorageVideoSource($ttl),
                VideoProvider::YouTube->value => new YouTubeVideoSource(
                    (string) config('video.youtube.embed_host', 'www.youtube.com'),
                ),
            ]);
        });
    }
}
