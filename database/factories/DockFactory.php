<?php

namespace Database\Factories;

use App\Models\Dock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dock>
 */
class DockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = Dock::query()->max('number') + 1;

        return [
            'number' => $number,
            'name' => "dock{$number}",
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
