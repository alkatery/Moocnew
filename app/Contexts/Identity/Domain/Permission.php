<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Domain;

/**
 * Fine-grained permissions guarded by Gates/Policies. The set grows with
 * each milestone; today it covers the only privileged resource that
 * exists — the platform settings, including the payments master switch
 * (PRD §4: Super Admin manages the payment-enable flag).
 */
enum Permission: string
{
    case ManageSettings = 'settings.manage';

    /**
     * Map of which roles are granted which permissions. Seeded into the
     * spatie permission tables; the Super Admin additionally bypasses all
     * checks via a Gate::before hook.
     *
     * @return array<string, list<string>>
     */
    public static function roleMatrix(): array
    {
        return [
            Role::SuperAdmin->value => [
                self::ManageSettings->value,
            ],
            Role::Supervisor->value => [],
            Role::Instructor->value => [],
            Role::Student->value => [],
            Role::Visitor->value => [],
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
