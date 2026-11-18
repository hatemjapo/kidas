<?php

namespace App\Models;

use App\Enums\Shift;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['starts_at', 'ends_at'])]
class ShiftDefault extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift' => Shift::class,
        ];
    }
}
