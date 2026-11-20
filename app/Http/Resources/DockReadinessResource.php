<?php

namespace App\Http\Resources;

use App\Data\DockReadiness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DockReadiness
 */
class DockReadinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'window_open' => $this->windowOpen,
            'dock_free' => $this->dockFree,
            'busy_until' => $this->busyUntil?->toIso8601ZuluString(),
            'poll_seconds' => (int) config('booking.status_poll_seconds'),
        ];
    }
}
