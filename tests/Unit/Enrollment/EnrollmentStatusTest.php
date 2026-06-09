<?php

declare(strict_types=1);

use App\Contexts\Enrollment\Domain\EnrollmentStatus;

it('moves a pending enrollment to active or refunded only', function () {
    expect(EnrollmentStatus::Pending->canTransitionTo(EnrollmentStatus::Active))->toBeTrue();
    expect(EnrollmentStatus::Pending->canTransitionTo(EnrollmentStatus::Completed))->toBeFalse();
});

it('moves an active enrollment to completed, expired or refunded', function () {
    expect(EnrollmentStatus::Active->canTransitionTo(EnrollmentStatus::Completed))->toBeTrue();
    expect(EnrollmentStatus::Active->canTransitionTo(EnrollmentStatus::Expired))->toBeTrue();
    expect(EnrollmentStatus::Active->canTransitionTo(EnrollmentStatus::Refunded))->toBeTrue();
});

it('treats a refunded enrollment as terminal', function () {
    expect(EnrollmentStatus::Refunded->allowedTransitions())->toBe([]);
});

it('grants access only while active or completed', function () {
    expect(EnrollmentStatus::Active->grantsAccess())->toBeTrue();
    expect(EnrollmentStatus::Completed->grantsAccess())->toBeTrue();
    expect(EnrollmentStatus::Pending->grantsAccess())->toBeFalse();
    expect(EnrollmentStatus::Expired->grantsAccess())->toBeFalse();
});
