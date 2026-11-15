<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('rejects an unknown role at the database level', function () {
    expect(fn () => DB::table('users')->insert([
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'irrelevant',
        'role' => 'manager',
    ]))->toThrow(QueryException::class);
});

test('accepts every role defined by the enum', function (string $role) {
    DB::table('users')->insert([
        'name' => 'Someone',
        'email' => $role.'@example.com',
        'password' => 'irrelevant',
        'role' => $role,
    ]);

    expect(DB::table('users')->where('role', $role)->exists())->toBeTrue();
})->with(['admin', 'staff', 'supplier']);
