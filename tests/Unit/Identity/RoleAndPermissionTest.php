<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Consent\ConsentType;
use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Identity\Domain\Role;

it('defines exactly the five platform roles', function () {
    expect(Role::values())->toEqual([
        'super_admin',
        'supervisor',
        'instructor',
        'student',
        'visitor',
    ]);
});

it('registers new accounts as students by default', function () {
    expect(Role::default())->toBe(Role::Student);
});

it('grants settings management only to the super admin', function () {
    $matrix = Permission::roleMatrix();

    expect($matrix[Role::SuperAdmin->value])->toContain(Permission::ManageSettings->value);

    foreach ([Role::Supervisor, Role::Instructor, Role::Student, Role::Visitor] as $role) {
        expect($matrix[$role->value])->not->toContain(Permission::ManageSettings->value);
    }
});

it('requires privacy-policy and data-processing consent', function () {
    expect(ConsentType::required())->toEqual([
        ConsentType::PrivacyPolicy,
        ConsentType::DataProcessing,
    ]);
});
