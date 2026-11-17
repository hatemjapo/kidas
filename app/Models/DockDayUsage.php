<?php

namespace App\Models;

use App\Enums\DockUsage;
use App\Enums\Shift;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usage'])]
class DockDayUsage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift' => Shift::class,
            'usage' => DockUsage::class,
        ];
    }

    /**
     * @return BelongsTo<CalendarDay, $this>
     */
    public function calendarDay(): BelongsTo
    {
        return $this->belongsTo(CalendarDay::class);
    }

    /**
     * @return BelongsTo<Dock, $this>
     */
    public function dock(): BelongsTo
    {
        return $this->belongsTo(Dock::class);
    }
}
