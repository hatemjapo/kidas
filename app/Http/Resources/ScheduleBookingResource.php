<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class ScheduleBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
            'pallets' => $this->pallets,
            'order_number' => $this->order_number,
            'license_plate' => $this->license_plate,
            'company' => $this->supplier instanceof Supplier
                ? $this->supplier->company_name
                : $this->carrier_name,
        ];
    }
}
