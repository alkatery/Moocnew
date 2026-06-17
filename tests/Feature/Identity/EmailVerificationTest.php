<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config()->set('app.frontend_url', 'http://localhost:3000');
});

function signedVerifyUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => $hash ?? sha1($user->getEmailForVerification()),
    ]);
}

it('blocks login for an unverified account with a resend hint', function () {
    User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'password' => 'password1234',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'password1234',
    ])->assertStatus(403)->assertJsonPath('code', 'email_unverified');
});

it('verifies the email from a valid signed link and returns a token', function () {
    $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);

    $this->getJson(signedVerifyUrl($user))
        ->assertOk()
        ->assertJsonStructure(['message', 'user' => ['id', 'email'], 'token']);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('lets a verified user log in and obtain a token', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'password' => 'password1234',
    ]);

    $this->getJson(signedVerifyUrl($user))->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'password1234',
    ])->assertOk()->assertJsonStructure(['token', 'user' => ['id']]);
});

it('rejects a tampered verification hash', function () {
    $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);

    $this->getJson(signedVerifyUrl($user, hash: sha1('attacker@example.com')))
        ->assertStatus(403)
        ->assertJsonPath('code', 'invalid_verification');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an unsigned verification request', function () {
    $user = User::factory()->unverified()->create();

    // Hitting the route without the signature is forbidden by `signed`.
    $this->getJson("/api/v1/auth/email/verify/{$user->getKey()}/".sha1($user->email))
        ->assertStatus(403);
});

it('resends the verification mail for an unverified account', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);

    $this->postJson('/api/v1/auth/email/resend', ['email' => 'pending@example.com'])
        ->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not reveal whether an email exists on resend', function () {
    Notification::fake();

    // Unknown email — still a generic 200, and nothing is sent.
    $this->postJson('/api/v1/auth/email/resend', ['email' => 'nobody@example.com'])
        ->assertOk();

    // Already-verified email — generic 200, nothing re-sent.
    $verified = User::factory()->create(['email' => 'done@example.com']);
    $this->postJson('/api/v1/auth/email/resend', ['email' => 'done@example.com'])
        ->assertOk();

    Notification::assertNothingSent();
});
