<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;

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

test('falls back to staff when no role is given', function () {
    DB::table('users')->insert([
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'password' => 'irrelevant',
    ]);

    expect(DB::table('users')->where('email', 'someone@example.com')->value('role'))->toBe('staff');
});

test('lets filament create a user who is kept out of the panel', function () {
    artisan('make:filament-user', [
        '--name' => 'Dock Admin',
        '--email' => 'dock@kidas.test',
        '--password' => 'password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'dock@kidas.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Staff)
        ->and($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});
