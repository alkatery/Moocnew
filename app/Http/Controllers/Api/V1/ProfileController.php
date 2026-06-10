<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Engagement\Infrastructure\Persistence\Badge;
use App\Contexts\Engagement\Infrastructure\Persistence\LearnerStat;
use App\Contexts\Identity\Domain\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Public profile pages: a learner's achievements (points, badges, earned
 * certificates) and an instructor's public page (bio, courses, rating).
 */
final class ProfileController extends Controller
{
    public function learner(User $user): JsonResponse
    {
        $stat = LearnerStat::query()->where('user_id', $user->id)->first();
        $badges = Badge::query()->where('user_id', $user->id)->pluck('badge');

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->with(['course:id,title', 'path:id,title'])
            ->latest('issued_at')
            ->get()
            ->map(fn (Certificate $c): array => [
                'title' => $c->subjectTitle(),
                'type' => $c->learning_path_id !== null ? 'learning_path' : 'course',
                'grade' => $c->grade,
                'issued_at' => $c->issued_at->toIso8601String(),
                'verification_uuid' => $c->verification_uuid,
            ]);

        return response()->json([
            'data' => [
                'name' => $user->name,
                'joined_at' => $user->created_at?->toIso8601String(),
                'points' => $stat?->points ?? 0,
                'current_streak' => $stat?->current_streak ?? 0,
                'badges' => $badges,
                'certificates' => $certificates,
            ],
        ]);
    }

    public function instructor(User $user): JsonResponse
    {
        abort_unless($user->hasRole(Role::Instructor->value), 404);

        $user->load('instructorProfile');

        $courses = Course::query()
            ->where('instructor_id', $user->id)
            ->where('status', CourseStatus::Published->value)
            ->withAvg('reviews', 'rating')
            ->withCount(['reviews', 'enrollments'])
            ->latest('published_at')
            ->get();

        $ratings = $courses->pluck('reviews_avg_rating')->filter();

        return response()->json([
            'data' => [
                'name' => $user->name,
                'bio' => $user->instructorProfile?->bio,
                'social_links' => $user->instructorProfile?->social_links ?? (object) [],
                'stats' => [
                    'courses' => $courses->count(),
                    'learners' => (int) $courses->sum('enrollments_count'),
                    'rating' => $ratings->isNotEmpty() ? round((float) $ratings->avg(), 1) : null,
                ],
                'courses' => $courses->map(fn (Course $c): array => [
                    'title' => $c->title,
                    'slug' => $c->slug,
                    'cover_image' => $c->cover_image,
                    'rating' => $c->reviews_avg_rating !== null ? round((float) $c->reviews_avg_rating, 1) : null,
                ]),
            ],
        ]);
    }
}
