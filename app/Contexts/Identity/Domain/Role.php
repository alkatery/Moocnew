<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Domain;

/**
 * The five platform roles (PRD §4). The enum is the single source of truth
 * for role names; the spatie `roles` table rows are seeded from these
 * cases, and authorization checks reference {@see self::value} rather than
 * loose string literals.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Supervisor = 'supervisor';
    case Instructor = 'instructor';
    case Student = 'student';
    case Visitor = 'visitor';

    /**
     * The role assigned to a freshly registered account.
     */
    public static function default(): self
    {
        return self::Student;
    }

    /**
     * Human-facing Arabic label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'الإدارة العليا',
            self::Supervisor => 'المشرف',
            self::Instructor => 'المدرس',
            self::Student => 'الطالب',
            self::Visitor => 'الزائر',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
