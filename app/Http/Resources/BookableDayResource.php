<?php

namespace App\Http\Resources;

use App\Data\BookableDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookableDay
 */
class BookableDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->toDateString(),
            'slots' => SlotResource::collection($this->slots),
        ];
    }
}
