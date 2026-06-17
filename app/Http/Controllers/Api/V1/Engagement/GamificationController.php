<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Contexts\Engagement\Infrastructure\Persistence\Badge;
use App\Contexts\Engagement\Infrastructure\Persistence\LearnerStat;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The learner's own gamification standing and the public leaderboard.
 */
final class GamificationController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $stat = LearnerStat::query()->firstOrCreate(['user_id' => $userId]);
        $badges = Badge::query()->where('user_id', $userId)->pluck('badge');

        $rank = LearnerStat::query()->where('points', '>', $stat->points)->count() + 1;

        return response()->json([
            'data' => [
                'points' => $stat->points,
                'current_streak' => $stat->current_streak,
                'longest_streak' => $stat->longest_streak,
                'rank' => $rank,
                'badges' => $badges,
            ],
        ]);
    }

    public function leaderboard(): JsonResponse
    {
        $top = LearnerStat::query()
            ->where('points', '>', 0)
            ->with('user:id,name')
            ->orderByDesc('points')
            ->limit(20)
            ->get()
            ->values()
            ->map(fn (LearnerStat $s, int $i): array => [
                'rank' => $i + 1,
                'name' => $s->user?->name ?? '—',
                'points' => $s->points,
                'current_streak' => $s->current_streak,
            ]);

        return response()->json(['data' => $top]);
    }
}
