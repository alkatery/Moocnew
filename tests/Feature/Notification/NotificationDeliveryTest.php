<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Notification\Domain\SmsSender;
use App\Models\User;

it('delivers a real SMS through the SmsSender and records an in-app notification', function () {
    // Spy SMS gateway capturing what would be sent.
    $sent = [];
    $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
    {
        public function __construct(public array &$sent) {}

        public function send(string $to, string $message): void
        {
            $this->sent[] = ['to' => $to, 'message' => $message];
        }
    });

    $course = Course::factory()->published()->create(['title' => 'دورة الاختبار']);
    $user = User::factory()->create(['phone' => '+966512345678']);

    app(EnrollmentService::class)->enroll($user, $course);

    // SMS captured by the spy.
    expect($sent)->toHaveCount(1);
    expect($sent[0]['to'])->toBe('+966512345678');
    expect($sent[0]['message'])->toContain('دورة الاختبار');

    // In-app (database) notification stored.
    expect($user->fresh()->notifications()->count())->toBe(1);
});

it('does not send an SMS when the learner has no phone number', function () {
    $sent = [];
    $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
    {
        public function __construct(public array &$sent) {}

        public function send(string $to, string $message): void
        {
            $this->sent[] = $to;
        }
    });

    $course = Course::factory()->published()->create();
    $user = User::factory()->create(['phone' => null]);

    app(EnrollmentService::class)->enroll($user, $course);

    expect($sent)->toBeEmpty();
    // The in-app notification is still recorded.
    expect($user->fresh()->notifications()->count())->toBe(1);
});
