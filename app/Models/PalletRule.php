<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['duration_minutes'])]
class PalletRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_pallets' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }
}
