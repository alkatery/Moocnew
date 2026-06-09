<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Consent\ConsentType;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'سارة المثال',
        'email' => 'sara@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
        'country' => 'SA',
        'consents' => [
            ConsentType::PrivacyPolicy->value,
            ConsentType::DataProcessing->value,
        ],
    ], $overrides);
}

it('registers a user, issues a token and assigns the student role', function () {
    $response = $this->postJson('/api/v1/auth/register', validRegistrationPayload());

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'email', 'roles']])
        ->assertJsonPath('user.roles', [Role::Student->value]);

    $user = User::query()->where('email', 'sara@example.com')->firstOrFail();
    expect($user->country)->toBe('SA');
    expect($user->locale)->toBe('ar'); // default
});

it('records the PDPL consents granted at sign-up with the policy version', function () {
    config()->set('platform.pdpl.policy_version', '2026-06-01');

    $this->postJson('/api/v1/auth/register', validRegistrationPayload())->assertCreated();

    $user = User::query()->where('email', 'sara@example.com')->firstOrFail();

    expect($user->consents)->toHaveCount(2);
    expect($user->consents->pluck('policy_version')->unique()->all())->toEqual(['2026-06-01']);
    expect($user->consents->pluck('type.value')->sort()->values()->all())
        ->toEqual(['data_processing', 'privacy_policy']);
});

it('writes an audit-trail entry for the registration', function () {
    $this->postJson('/api/v1/auth/register', validRegistrationPayload())->assertCreated();

    $this->assertDatabaseHas('activity_logs', [
        'event' => 'user.registered',
    ]);
});

it('rejects registration when a required consent is missing', function () {
    $response = $this->postJson('/api/v1/auth/register', validRegistrationPayload([
        'consents' => [ConsentType::PrivacyPolicy->value], // missing data_processing
    ]));

    $response->assertStatus(422)->assertJsonValidationErrors('consents');
    $this->assertDatabaseCount('users', 0);
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'sara@example.com']);

    $this->postJson('/api/v1/auth/register', validRegistrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('rejects a weak or unconfirmed password', function () {
    $this->postJson('/api/v1/auth/register', validRegistrationPayload([
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->assertStatus(422)->assertJsonValidationErrors('password');
});
