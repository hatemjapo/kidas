<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AdminRescheduleBookingRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
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
            'reason' => ['nullable', 'string', 'max:500'],
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
