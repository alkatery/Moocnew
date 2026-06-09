<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Domain\Settings;

/**
 * Strongly-typed registry of the platform settings that the application
 * reads at runtime. Keeping the keys in one enum keeps call sites honest
 * (no stray string literals) and gives us a single place to document the
 * type each key is expected to hold.
 */
enum SettingKey: string
{
    /**
     * Master switch for the Commerce bounded context (orders, payments,
     * invoices, ledger, payouts). See ADR-0004. Stored as a boolean.
     */
    case PaymentsEnabled = 'payments.enabled';

    /**
     * The expected PHP value type for this key, used by the repository to
     * cast values consistently on the way in and out of storage.
     */
    public function valueType(): SettingValueType
    {
        return match ($this) {
            self::PaymentsEnabled => SettingValueType::Boolean,
        };
    }
}
