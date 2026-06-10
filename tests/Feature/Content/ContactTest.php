<?php

declare(strict_types=1);

use App\Contexts\Content\Domain\ContactMessageStatus;
use App\Contexts\Content\Infrastructure\Persistence\ContactMessage;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('stores a valid contact message from a guest', function () {
    $this->postJson('/api/v1/contact', [
        'name' => 'سارة العتيبي',
        'email' => 'sara@example.com',
        'subject' => 'استفسار عن الشهادات',
        'message' => 'هل الشهادات معتمدة وقابلة للتحقق؟',
    ])->assertCreated()
        ->assertJsonPath('data.status', ContactMessageStatus::New->value);

    $this->assertDatabaseHas('contact_messages', [
        'email' => 'sara@example.com',
        'status' => ContactMessageStatus::New->value,
    ]);
});

it('rejects an invalid submission', function () {
    $this->postJson('/api/v1/contact', [
        'name' => 'بدون بريد',
        'email' => 'not-an-email',
        'subject' => '',
        'message' => '',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'subject', 'message']);
});

it('rate-limits the contact form', function () {
    $payload = [
        'name' => 'مرسل متكرر',
        'email' => 'spam@example.com',
        'subject' => 'تكرار',
        'message' => 'رسالة',
    ];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/contact', $payload)->assertCreated();
    }

    $this->postJson('/api/v1/contact', $payload)->assertStatus(429);
});

it('keeps the triage list away from guests and students', function () {
    $this->getJson('/api/v1/admin/contact-messages')->assertUnauthorized();

    Sanctum::actingAs(userWithRole(Role::Student));
    $this->getJson('/api/v1/admin/contact-messages')->assertForbidden();
});

it('lets a supervisor list and filter messages', function () {
    ContactMessage::factory()->count(2)->create();
    ContactMessage::factory()->create([
        'status' => ContactMessageStatus::Handled,
        'handled_at' => now(),
    ]);

    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->getJson('/api/v1/admin/contact-messages')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $this->getJson('/api/v1/admin/contact-messages?status=new')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets a supervisor mark a message handled', function () {
    $message = ContactMessage::factory()->create();

    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson("/api/v1/admin/contact-messages/{$message->id}/handle")
        ->assertOk()
        ->assertJsonPath('data.status', ContactMessageStatus::Handled->value);

    expect($message->fresh()->handled_at)->not->toBeNull();
});
