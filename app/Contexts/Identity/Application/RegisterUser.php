<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Application;

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Registers a new account: creates the user, assigns the default role,
 * records the PDPL consents granted at sign-up, and writes an audit entry
 * — all in a single transaction so a partial registration can never
 * persist (PRD §5.أ, §7).
 */
final class RegisterUser
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function handle(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                ...Arr::only($data->profile, [
                    'phone',
                    'country',
                    'education_level',
                    'interests',
                    'locale',
                    'timezone',
                ]),
            ]);

            $user->assignRole(Role::default()->value);

            $policyVersion = (string) config('platform.pdpl.policy_version');
            $now = Date::now();

            foreach ($data->consents as $consent) {
                $user->consents()->create([
                    'type' => $consent,
                    'policy_version' => $policyVersion,
                    'consented_at' => $now,
                ]);
            }

            $this->activity->log('user.registered', $user, $user);

            return $user;
        });
    }
}
