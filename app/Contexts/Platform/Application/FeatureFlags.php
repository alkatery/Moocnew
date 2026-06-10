<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Application;

use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;

/**
 * Application service exposing the platform's runtime feature flags.
 *
 * Today the only flag is the Commerce master switch (`payments.enabled`),
 * which gates the entire paid mode of the platform (see ADR-0004 and the
 * PRD §1). Callers should depend on this service rather than reading the
 * setting directly, so the default-resolution policy lives in one place.
 */
final class FeatureFlags
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Whether the Commerce context (orders, payments, invoices, ledger,
     * payouts) is active. When false the platform operates fully in free
     * mode: every course enrolls directly and Commerce routes/UI stay
     * hidden. The stored setting wins; the config value only supplies the
     * default before an admin has ever toggled it.
     */
    public function paymentsEnabled(): bool
    {
        $stored = $this->settings->get(SettingKey::PaymentsEnabled);

        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('platform.payments.enabled_default', false);
    }

    /**
     * The active AI-assistant engine: 'off', 'rules' or 'claude'. The stored
     * setting wins; config supplies the default before an admin has chosen.
     * Returned as a raw string to keep this context free of Assistant types.
     */
    public function assistantMode(): string
    {
        $stored = $this->settings->get(SettingKey::AssistantMode);

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return (string) config('ai.default_mode', 'off');
    }
}
