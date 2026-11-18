<?php

namespace App\Enums;

enum Shift: string
{
    case Early = 'early';
    case Late = 'late';

    public function label(): string
    {
        return match ($this) {
            self::Early => 'Early shift',
            self::Late => 'Late shift',
        };
    }
}
