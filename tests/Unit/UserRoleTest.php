<?php

use App\Enums\UserRole;

test('stores the role values expected by the database', function () {
    expect(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))
        ->toBe(['admin', 'staff', 'supplier']);
});
