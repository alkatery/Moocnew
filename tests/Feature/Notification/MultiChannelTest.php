<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Notification\Domain\PushSender;
use App\Contexts\Notification\Domain\WhatsAppSender;
use App\Models\User;

it('delivers enrollment confirmation over WhatsApp and Push as well', function () {
    $whatsapp = [];
    $push = [];

    $this->app->instance(WhatsAppSender::class, new class($whatsapp) implements WhatsAppSender
    {
        public function __construct(public array &$sent) {}

        public function send(string $to, string $message): void
        {
            $this->sent[] = ['to' => $to, 'message' => $message];
        }
    });

    $this->app->instance(PushSender::class, new class($push) implements PushSender
    {
        public function __construct(public array &$sent) {}

        public function send(string $recipient, string $title, array $payload = []): void
        {
            $this->sent[] = ['to' => $recipient, 'title' => $title];
        }
    });

    $course = Course::factory()->published()->create(['title' => 'دورة القنوات']);
    $user = User::factory()->create(['phone' => '+966500000000']);

    app(EnrollmentService::class)->enroll($user, $course);

    expect($whatsapp)->toHaveCount(1);
    expect($whatsapp[0]['message'])->toContain('دورة القنوات');
    expect($push)->toHaveCount(1);
    expect($push[0]['to'])->toBe((string) $user->id);
});
