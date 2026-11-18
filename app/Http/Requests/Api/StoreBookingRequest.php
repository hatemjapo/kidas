<?php

namespace App\Http\Requests\Api;

use App\Enums\DockUsage;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
            'type' => ['required', Rule::enum(DockUsage::class)],
            'pallets' => ['required', 'integer', 'min:1', 'max:'.(int) config('booking.max_pallets')],
            'starts_at' => ['required', 'date'],
            'order_number' => ['required', 'string', 'max:255'],
            'driver_phone' => ['required', 'string', 'max:50'],
            'vehicle_id' => [
                'required_without:license_plate',
                'nullable',
                'integer',
                Rule::exists('vehicles', 'id')->where('supplier_id', $supplier->id),
            ],
            'license_plate' => ['required_without:vehicle_id', 'nullable', 'string', 'max:15'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.exists' => 'This vehicle is not one of yours.',
        ];
    }
}
