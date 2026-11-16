<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

/**
 * @return Testable<Login>
 */
function submitPanelLogin(string $email, string $password): Testable
{
    Filament::setCurrentPanel('admin');

    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');
}

test('redirects guests from the admin panel to the login page', function () {
    get('/admin')->assertRedirect('/admin/login');
});

test('shows the login page to guests', function () {
    get('/admin/login')->assertOk();
});

test('lets an admin into the panel', function () {
    actingAs(User::factory()->admin()->create());

    get('/admin')->assertOk();
});

test('denies the panel to warehouse staff', function () {
    actingAs(User::factory()->staff()->create());

    get('/admin')->assertForbidden();
});

test('denies the panel to suppliers', function () {
    actingAs(User::factory()->supplier()->create());

    get('/admin')->assertForbidden();
});

test('signs an admin in through the panel login form', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@kidas.test']);

    submitPanelLogin('admin@kidas.test', 'password')->assertHasNoFormErrors();

    assertAuthenticatedAs($admin);
});

test('turns warehouse staff away at the panel login form', function () {
    User::factory()->staff()->create(['email' => 'staff@kidas.test']);

    submitPanelLogin('staff@kidas.test', 'password')->assertHasFormErrors(['email']);

    assertGuest();
});

test('turns a supplier away at the panel login form', function () {
    User::factory()->supplier()->create(['email' => 'supplier@kidas.test']);

    submitPanelLogin('supplier@kidas.test', 'password')->assertHasFormErrors(['email']);

    assertGuest();
});

test('refuses a wrong password at the panel login form', function () {
    User::factory()->admin()->create(['email' => 'admin@kidas.test']);

    submitPanelLogin('admin@kidas.test', 'wrong-password')->assertHasFormErrors(['email']);

    assertGuest();
});
