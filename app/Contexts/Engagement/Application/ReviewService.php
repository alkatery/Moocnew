<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Application;

use App\Contexts\Engagement\Domain\PointRule;
use App\Contexts\Engagement\Infrastructure\Persistence\CourseReview;
use App\Models\User;

/**
 * Course ratings & reviews. A learner has a single review per course;
 * writing one for the first time awards engagement points.
 */
final class ReviewService
{
    public function __construct(
        private readonly GamificationService $gamification,
    ) {}

    public function submit(User $user, int $courseId, int $rating, ?string $comment): CourseReview
    {
        $existing = CourseReview::query()
            ->where('course_id', $courseId)
            ->where('user_id', $user->getKey())
            ->first();

        $review = CourseReview::query()->updateOrCreate(
            ['course_id' => $courseId, 'user_id' => $user->getKey()],
            ['rating' => $rating, 'comment' => $comment],
        );

        if ($existing === null) {
            $this->gamification->award($user->getKey(), PointRule::ReviewWritten, "review:{$review->id}");
        }

        return $review;
    }

    /**
     * @return array{average: float, count: int, distribution: array<int,int>}
     */
    public function summary(int $courseId): array
    {
        $reviews = CourseReview::query()->where('course_id', $courseId)->get(['rating']);

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $review) {
            $distribution[$review->rating]++;
        }

        return [
            'average' => $reviews->count() > 0 ? round($reviews->avg('rating'), 1) : 0.0,
            'count' => $reviews->count(),
            'distribution' => $distribution,
        ];
    }
}
