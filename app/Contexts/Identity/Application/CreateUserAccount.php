<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Application;

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a user account from the admin panel (PRD §4) — the path by which
 * staff onboard instructors and supervisors, who never self-register. The
 * account is created with the requested role in a single transaction and
 * the action is audited.
 */
final class CreateUserAccount
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function handle(User $actor, string $name, string $email, string $password, Role $role): User
    {
        return DB::transaction(function () use ($actor, $name, $email, $password, $role): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->assignRole($role->value);

            $this->activity->log('user.created_by_admin', $actor, $user, [
                'role' => $role->value,
            ]);

            return $user;
        });
    }
}
