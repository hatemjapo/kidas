<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory()->approved(),
            'license_plate' => 'HH'.Str::upper(Str::random(2)).fake()->unique()->numberBetween(100, 9999),
        ];
    }
}
