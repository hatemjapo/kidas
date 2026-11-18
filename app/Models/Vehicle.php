<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['license_plate'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    public static function normalizePlate(string $licensePlate): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtoupper($licensePlate));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function licensePlate(): Attribute
    {
        return Attribute::set(fn (string $value): string => self::normalizePlate($value));
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
