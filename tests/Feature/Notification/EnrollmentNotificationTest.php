<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Notification\Application\NotificationPreferences;
use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\EnrollmentConfirmedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('notifies the learner across all enabled channels on enrollment', function () {
    Notification::fake();

    $course = Course::factory()->published()->create();
    $user = User::factory()->create(['phone' => '+966500000000']);

    app(EnrollmentService::class)->enroll($user, $course);

    Notification::assertSentTo(
        $user,
        EnrollmentConfirmedNotification::class,
        function ($notification, array $channels) {
            return in_array('mail', $channels, true)
                && in_array('sms', $channels, true)
                && in_array('database', $channels, true);
        },
    );
});

it('omits a channel the learner has disabled', function () {
    $course = Course::factory()->published()->create();
    $user = User::factory()->create(['phone' => '+966500000000']);

    // Opt out of SMS for enrollment confirmations.
    app(NotificationPreferences::class)->set(
        $user,
        NotificationType::EnrollmentConfirmed,
        NotificationChannel::Sms,
        false,
    );

    Notification::fake();
    app(EnrollmentService::class)->enroll($user, $course);

    Notification::assertSentTo(
        $user,
        EnrollmentConfirmedNotification::class,
        fn ($notification, array $channels) => ! in_array('sms', $channels, true)
            && in_array('mail', $channels, true),
    );
});
