<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Ensures a Super Admin account exists so a fresh deployment is usable
 * immediately. Credentials come from the ADMIN_EMAIL / ADMIN_PASSWORD
 * environment variables (passed by the provisioning script), with
 * trial-friendly defaults. Idempotent: never duplicates or overwrites
 * an existing account's password.
 */
final class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@mooc.test');
        $password = env('ADMIN_PASSWORD', 'AdminMooc2026');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = new User([
                'name' => 'مدير المنصة',
                'email' => $email,
                'password' => $password, // hashed by the model's `hashed` cast
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        if (! $user->hasRole(Role::SuperAdmin->value)) {
            $user->assignRole(Role::SuperAdmin->value);
        }

        $this->command?->info("Super Admin ready: {$email}");
    }
}
