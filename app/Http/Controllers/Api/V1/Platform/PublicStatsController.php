<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregate-only public counters for the marketing pages (home/about).
 * No personal data is exposed (PDPL) and the result is cached because it
 * sits on the most-visited page of the site.
 */
final class PublicStatsController extends Controller
{
    private const CACHE_KEY = 'platform:public-stats';

    private const CACHE_TTL_SECONDS = 600;

    public function __invoke(): JsonResponse
    {
        $stats = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, static fn (): array => [
            'courses' => Course::query()->where('status', CourseStatus::Published->value)->count(),
            'learners' => User::role(Role::Student->value)->count(),
            'instructors' => User::role(Role::Instructor->value)->count(),
            'enrollments' => Enrollment::query()->count(),
        ]);

        return response()->json(['data' => $stats]);
    }
}
