<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LogInUser;
use App\Actions\Auth\LogOutUser;
use App\Actions\Suppliers\RegisterSupplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterSupplierRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterSupplierRequest $request, RegisterSupplier $registerSupplier): JsonResponse
    {
        $user = $registerSupplier(
            $request->string('company_name')->toString(),
            $request->string('company_phone')->toString(),
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, LogInUser $logInUser): UserResource
    {
        $user = $logInUser(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return new UserResource($user);
    }

    public function logout(LogOutUser $logOutUser): Response
    {
        $logOutUser();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user();
        assert($user instanceof User);

        return new UserResource($user);
    }
}
