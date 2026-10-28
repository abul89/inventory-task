<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => ucfirst(fake()->words(2, true)),
            'code'        => strtoupper(fake()->unique()->bothify('PRD-####')),
            'price'       => fake()->randomFloat(2, 5, 500),
            'tax_percent' => fake()->randomElement([0, 5, 12, 18]),
            'stock'       => fake()->numberBetween(20, 200),
        ];
    }

    /** Usage: Product::factory()->outOfStock()->create() */
    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }
}
