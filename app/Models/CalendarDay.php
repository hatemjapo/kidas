<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['is_closed', 'opens_at', 'closes_at', 'early_start', 'early_end', 'late_start', 'late_end'])]
class CalendarDay extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_released' => 'boolean',
            'is_closed' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DockDayUsage, $this>
     */
    public function dockUsages(): HasMany
    {
        return $this->hasMany(DockDayUsage::class);
    }
}
