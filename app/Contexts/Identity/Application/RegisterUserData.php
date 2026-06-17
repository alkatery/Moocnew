<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Application;

use App\Contexts\Identity\Domain\Consent\ConsentType;

/**
 * Immutable input for {@see RegisterUser}. Decouples the registration use
 * case from the HTTP request shape.
 */
final readonly class RegisterUserData
{
    /**
     * @param  list<ConsentType>  $consents
     * @param  array<string, mixed>  $profile  Optional profile fields (country, phone, ...).
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public array $consents,
        public array $profile = [],
    ) {}
}
