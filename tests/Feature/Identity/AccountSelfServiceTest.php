<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Pest\PendingCalls\TestCall;
use Tests\TestCase;

function authedUser(array $attrs = []): array
{
    $user = User::factory()->create(array_merge(['password' => 'password1234'], $attrs));
    $token = $user->createToken('web')->plainTextToken;

    return [$user, $token];
}

function asUser(string $token): TestCall|TestCase
{
    return test()->withHeader('Authorization', "Bearer {$token}");
}

it('updates the editable profile fields', function () {
    [, $token] = authedUser(['locale' => 'ar']);

    asUser($token)->patchJson('/api/v1/account', [
        'name' => 'اسم محدّث',
        'locale' => 'en',
        'timezone' => 'Asia/Riyadh',
    ])->assertOk()->assertJsonPath('user.locale', 'en')->assertJsonPath('user.name', 'اسم محدّث');
});

it('rejects an invalid locale or timezone', function () {
    [, $token] = authedUser();

    asUser($token)->patchJson('/api/v1/account', ['locale' => 'fr'])
        ->assertStatus(422)->assertJsonValidationErrors('locale');
});

it('changes the password and revokes other sessions but keeps the current one', function () {
    [$user, $token] = authedUser();
    $user->createToken('mobile');

    asUser($token)->putJson('/api/v1/account/password', [
        'current_password' => 'password1234',
        'password' => 'NewStrong2026',
        'password_confirmation' => 'NewStrong2026',
    ])->assertOk();

    // The new password works, and only the current session survives.
    expect(Hash::check('NewStrong2026', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(1);

    // forgetGuards() drops the per-request guard cache so the next call
    // re-resolves the (still valid) current token from scratch.
    app('auth')->forgetGuards();
    asUser($token)->getJson('/api/v1/auth/me')->assertOk();
});

it('rejects a password change with the wrong current password', function () {
    [, $token] = authedUser();

    asUser($token)->putJson('/api/v1/account/password', [
        'current_password' => 'wrong-one',
        'password' => 'NewStrong2026',
        'password_confirmation' => 'NewStrong2026',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');
});

it('exports the user personal data', function () {
    [$user, $token] = authedUser(['country' => 'SA']);
    $user->consents()->create([
        'type' => 'privacy_policy',
        'policy_version' => '2026-06-01',
        'consented_at' => now(),
    ]);

    asUser($token)->getJson('/api/v1/account/export')
        ->assertOk()
        ->assertJsonPath('data.profile.country', 'SA')
        ->assertJsonPath('data.consents.0.type', 'privacy_policy')
        ->assertJsonStructure(['data' => ['profile', 'consents', 'enrollments', 'certificates']]);
});

it('anonymises the account on self-deletion and blocks further access', function () {
    [$user, $token] = authedUser(['email' => 'gone@example.com']);

    asUser($token)->deleteJson('/api/v1/account', [
        'current_password' => 'password1234',
    ])->assertNoContent();

    $fresh = User::withTrashed()->find($user->id);
    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->email)->not->toBe('gone@example.com')
        ->and($fresh->name)->toBe('مستخدم محذوف')
        ->and($fresh->tokens()->count())->toBe(0);

    // The token is dead — drop the guard cache so it re-resolves and fails.
    app('auth')->forgetGuards();
    asUser($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects self-deletion with the wrong password', function () {
    [, $token] = authedUser();

    asUser($token)->deleteJson('/api/v1/account', ['current_password' => 'nope'])
        ->assertStatus(422)->assertJsonValidationErrors('current_password');
});

it('requires authentication for account endpoints', function () {
    $this->getJson('/api/v1/account/export')->assertUnauthorized();
    $this->patchJson('/api/v1/account', [])->assertUnauthorized();
});
