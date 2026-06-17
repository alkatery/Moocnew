<?php

declare(strict_types=1);

use App\Contexts\Catalog\Domain\Course\CourseStatus;

it('allows a draft to be submitted for review', function () {
    expect(CourseStatus::Draft->canTransitionTo(CourseStatus::PendingReview))->toBeTrue();
});

it('does not allow a draft to be published directly', function () {
    expect(CourseStatus::Draft->canTransitionTo(CourseStatus::Published))->toBeFalse();
});

it('allows a course under review to be published or sent back', function () {
    expect(CourseStatus::PendingReview->canTransitionTo(CourseStatus::Published))->toBeTrue();
    expect(CourseStatus::PendingReview->canTransitionTo(CourseStatus::Draft))->toBeTrue();
});

it('knows when it represents a published course', function () {
    expect(CourseStatus::Published->isPublished())->toBeTrue();
    expect(CourseStatus::Draft->isPublished())->toBeFalse();
});
