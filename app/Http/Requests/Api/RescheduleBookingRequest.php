<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required_without:pallets', 'nullable', 'date'],
            'pallets' => [
                'required_without:starts_at',
                'nullable',
                'integer',
                'min:1',
                'max:'.(int) config('booking.max_pallets'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.required_without' => 'Send a new start time, a new pallet count, or both.',
            'pallets.required_without' => 'Send a new start time, a new pallet count, or both.',
        ];
    }
}
