<?php

namespace Database\Factories;

use App\Models\Medication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medication>
 */
class MedicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $initial = fake()->numberBetween(20, 120);

        return [
            'name' => fake()->unique()->word().' '.fake()->numberBetween(10, 500).' mg',
            'category' => fake()->randomElement(['Ağrı', 'Antibiyotik', 'Uyku', 'Bulantı']),
            'form' => 'tablet',
            'strength' => fake()->numberBetween(10, 500).' mg',
            'unit' => 'tablet',
            'quantity' => $initial,
            'initial_quantity' => $initial,
            'min_threshold' => (int) round($initial * 0.2),
            'expires_on' => now()->addYears(2)->toDateString(),
            'storage_note' => null,
        ];
    }

    /**
     * A medication at or below its reorder threshold.
     */
    public function low(): static
    {
        return $this->state(fn (array $attributes): array => ['quantity' => $attributes['min_threshold']]);
    }
}
