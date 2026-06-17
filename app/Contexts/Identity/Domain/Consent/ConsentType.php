<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Domain\Consent;

/**
 * The kinds of consent a user may grant under PDPL (PRD §5.أ, §7). Each
 * recorded consent is stamped with the policy version in force at the time
 * so that consent history remains auditable as policies evolve.
 */
enum ConsentType: string
{
    case PrivacyPolicy = 'privacy_policy';
    case DataProcessing = 'data_processing';

    public function label(): string
    {
        return match ($this) {
            self::PrivacyPolicy => 'سياسة الخصوصية',
            self::DataProcessing => 'معالجة البيانات',
        };
    }

    /**
     * Consents a user must grant to complete registration.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::PrivacyPolicy, self::DataProcessing];
    }
}
