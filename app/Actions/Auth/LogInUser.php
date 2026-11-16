<?php

namespace App\Actions\Auth;

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LogInUser
{
    public function __invoke(string $email, string $password): User
    {
        $guard = Auth::guard('web');
        $provider = $guard->getProvider();

        $credentials = ['email' => $email, 'password' => $password];
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $this->denyUnapprovedSupplier($user);

        $guard->login($user);

        Session::regenerate();

        return $user;
    }

    private function denyUnapprovedSupplier(User $user): void
    {
        if ($user->role !== UserRole::Supplier) {
            return;
        }

        $status = $user->supplier?->status;

        if ($status === SupplierStatus::Approved) {
            return;
        }

        throw new AccessDeniedHttpException($status === SupplierStatus::Rejected
            ? 'Your registration was rejected. Please contact the warehouse team.'
            : 'Your registration is still being reviewed. You will receive an email as soon as it is approved.');
    }
}
