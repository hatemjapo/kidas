<?php

namespace App\Http\Requests\Api;

use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $licensePlate = $this->input('license_plate');

        if (is_string($licensePlate)) {
            $this->merge(['license_plate' => Vehicle::normalizePlate($licensePlate)]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $supplier = $user->supplier;
        assert($supplier instanceof Supplier);

        return [
            'license_plate' => [
                'required',
                'string',
                'max:15',
                Rule::unique('vehicles', 'license_plate')->where('supplier_id', $supplier->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'license_plate.unique' => 'This license plate is already saved.',
        ];
    }
}
