<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Application;

use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erases a user's personal data while keeping referential integrity intact
 * (PDPL «right to erasure» balanced with audit/finance record integrity,
 * PRD §7). PII is overwritten and the account soft-deleted rather than hard
 * deleted, so enrolments, certificates and ledger rows keep a valid — but
 * now anonymous — owner.
 *
 * Shared by self-service deletion (A2) and admin-initiated retirement (A3).
 */
final class AnonymizeUser
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function handle(User $user, string $event, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $event, $actor): void {
            // Kill every session first so no live token survives erasure.
            $user->tokens()->delete();

            $token = Str::uuid()->toString();

            $user->forceFill([
                'name' => 'مستخدم محذوف',
                'email' => "deleted-{$token}@deleted.invalid",
                'phone' => null,
                'country' => null,
                'education_level' => null,
                'interests' => null,
                'disabled_at' => Date::now(),
            ])->save();

            // The actor defaults to the user themselves (self-service delete).
            $this->activity->log($event, $actor ?? $user, $user);

            $user->delete();
        });
    }
}
