<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
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
            'driver_phone' => $this->driver_phone,
            'dock_number' => $this->dock->number,
            'arrived_at' => $this->arrived_at?->toIso8601ZuluString(),
            'arrived_plate' => $this->arrived_plate,
            'plate_mismatch' => $this->plate_mismatch,
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'cancel_reason' => $this->cancel_reason,
            'change_deadline_at' => $this->starts_at
                ->copy()
                ->subHours((int) config('booking.change_deadline_hours'))
                ->toIso8601ZuluString(),
            'history' => BookingEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
