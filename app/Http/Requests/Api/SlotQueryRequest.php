<?php

namespace App\Http\Requests\Api;

use App\Enums\DockUsage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SlotQueryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DockUsage::class)],
            'pallets' => ['required', 'integer', 'min:1', 'max:'.(int) config('booking.max_pallets')],
            'after' => ['nullable', 'date', 'prohibits:before'],
            'before' => ['nullable', 'date', 'prohibits:after'],
        ];
    }
}
