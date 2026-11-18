<?php

namespace App\Http\Controllers\Api;

use App\Actions\Suppliers\UpdateSupplierProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): ProfileResource
    {
        return new ProfileResource($this->supplierOf($request));
    }

    public function update(UpdateProfileRequest $request, UpdateSupplierProfile $updateSupplierProfile): ProfileResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $supplier = $updateSupplierProfile(
            $user,
            $request->string('name')->toString(),
            $request->string('company_name')->toString(),
            $request->string('company_phone')->toString(),
        );

        return new ProfileResource($supplier);
    }

    private function supplierOf(Request $request): Supplier
    {
        $user = $request->user();
        assert($user instanceof User);

        $supplier = $user->supplier;
        assert($supplier instanceof Supplier);

        return $supplier;
    }
}
