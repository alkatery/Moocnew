<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Application\CoursePublishing;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use Illuminate\Http\Request;

final class CoursePublishingController extends Controller
{
    public function __construct(
        private readonly CoursePublishing $publishing,
    ) {}

    public function submit(Request $request, Course $course): CourseResource
    {
        abort_unless($request->user()->can('submit', $course), 403);

        return new CourseResource($this->publishing->submitForReview($course, $request->user()));
    }

    public function approve(Request $request, Course $course): CourseResource
    {
        abort_unless($request->user()->can('review', $course), 403);

        return new CourseResource($this->publishing->approve($course, $request->user()));
    }

    public function reject(Request $request, Course $course): CourseResource
    {
        abort_unless($request->user()->can('review', $course), 403);

        return new CourseResource($this->publishing->reject($course, $request->user()));
    }
}
