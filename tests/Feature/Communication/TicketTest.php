<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('lets a user open a ticket and reply', function () {
    Sanctum::actingAs(User::factory()->create());

    $ticketId = $this->postJson('/api/v1/community/tickets', [
        'subject' => 'مشكلة في الدخول', 'body' => 'لا أستطيع تسجيل الدخول',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/community/tickets/{$ticketId}/messages", ['body' => 'تفاصيل إضافية'])
        ->assertCreated();
});

it('forbids a different user from viewing someone else’s ticket', function () {
    Sanctum::actingAs($owner = User::factory()->create());
    $ticketId = $this->postJson('/api/v1/community/tickets', ['subject' => 's', 'body' => 'b'])->json('data.id');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/community/tickets/{$ticketId}")->assertForbidden();
});

it('lets a supervisor view, reply to and close any ticket', function () {
    Sanctum::actingAs(User::factory()->create());
    $ticketId = $this->postJson('/api/v1/community/tickets', ['subject' => 's', 'body' => 'b'])->json('data.id');

    Sanctum::actingAs(userWithRole(Role::Supervisor));
    $this->getJson("/api/v1/community/tickets/{$ticketId}")->assertOk();
    $this->postJson("/api/v1/community/tickets/{$ticketId}/messages", ['body' => 'سنساعدك'])->assertCreated();
    $this->postJson("/api/v1/community/tickets/{$ticketId}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');
});

it('rejects replies to a closed ticket', function () {
    Sanctum::actingAs($owner = User::factory()->create());
    $ticketId = $this->postJson('/api/v1/community/tickets', ['subject' => 's', 'body' => 'b'])->json('data.id');
    $this->postJson("/api/v1/community/tickets/{$ticketId}/close")->assertOk();

    $this->postJson("/api/v1/community/tickets/{$ticketId}/messages", ['body' => 'مرحبا'])->assertStatus(422);
});
