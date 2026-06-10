<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Contexts\Learning\Application\StudyPlanService;
use App\Contexts\Learning\Domain\StudyPlanStatus;
use App\Contexts\Learning\Infrastructure\Persistence\StudyPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreStudyPlanRequest;
use App\Http\Requests\Learning\UpdateStudyPlanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personal study plans (owner-only): gather courses into a plan with a
 * reminder cadence; the scheduler nudges the learner until it's done.
 */
final class StudyPlanController extends Controller
{
    public function __construct(
        private readonly StudyPlanService $plans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = StudyPlan::query()
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->get();

        return response()->json([
            'data' => $plans->map(fn (StudyPlan $plan): array => $this->payload($plan)),
        ]);
    }

    public function store(StoreStudyPlanRequest $request): JsonResponse
    {
        $plan = $this->plans->create(
            $request->user(),
            $request->validated('title'),
            (int) $request->validated('cadence_days'),
            $request->validated('target_date'),
            $request->validated('course_ids'),
        );

        return response()->json(['data' => $this->payload($plan)], 201);
    }

    public function update(UpdateStudyPlanRequest $request, StudyPlan $plan): JsonResponse
    {
        abort_unless($plan->user_id === $request->user()->getKey(), 404);

        $plan->update($request->safe()->only(['title', 'cadence_days', 'target_date']));

        if ($request->has('course_ids')) {
            $this->plans->syncItems($plan, $request->validated('course_ids'));

            // New items can reopen a previously completed plan.
            $progress = $this->plans->progress($plan);
            if ($plan->status === StudyPlanStatus::Completed && $progress['percent'] < 100) {
                $plan->update(['status' => StudyPlanStatus::Active, 'completed_at' => null]);
            }
        }

        return response()->json(['data' => $this->payload($plan->fresh())]);
    }

    public function destroy(Request $request, StudyPlan $plan): JsonResponse
    {
        abort_unless($plan->user_id === $request->user()->getKey(), 404);

        $plan->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StudyPlan $plan): array
    {
        $rows = $this->plans->itemsWithCompletion($plan);
        $progress = $this->plans->progress($plan);

        $next = $rows->firstWhere('completed', false);

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'cadence_days' => $plan->cadence_days,
            'target_date' => $plan->target_date?->toDateString(),
            'status' => $plan->status->value,
            'completed_at' => $plan->completed_at?->toIso8601String(),
            'progress' => $progress,
            'next_course' => $next !== null ? [
                'id' => $next['item']->course->id,
                'title' => $next['item']->course->title,
                'slug' => $next['item']->course->slug,
            ] : null,
            'items' => $rows->map(fn (array $row): array => [
                'course' => [
                    'id' => $row['item']->course->id,
                    'title' => $row['item']->course->title,
                    'slug' => $row['item']->course->slug,
                ],
                'completed' => $row['completed'],
            ])->values(),
        ];
    }
}
