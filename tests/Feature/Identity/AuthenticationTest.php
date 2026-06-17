<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('logs in with valid credentials and returns a token', function () {
    User::factory()->create([
        'email' => 'sara@example.com',
        'password' => 'password1234',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'password1234',
    ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
});

it('rejects invalid credentials with a generic error', function () {
    User::factory()->create([
        'email' => 'sara@example.com',
        'password' => 'password1234',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('throttles repeated failed logins to blunt brute-force and bcrypt DoS', function () {
    User::factory()->create([
        'email' => 'sara@example.com',
        'password' => 'password1234',
    ]);

    // خمس محاولات فاشلة تستنفد الحدّ (MAX_ATTEMPTS).
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'sara@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    // السادسة تُحظر برسالة «محاولات كثيرة» حتى بكلمة مرور صحيحة.
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'sara@example.com',
        'password' => 'password1234',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect($response->json('errors.email.0'))->toContain('محاولات دخول كثيرة');
});

it('returns the authenticated user from /me', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

it('rejects /me without authentication', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect($user->fresh()->tokens()->count())->toBe(0);
});
