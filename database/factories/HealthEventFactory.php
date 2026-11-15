<?php

namespace Database\Factories;

use App\Models\HealthEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthEvent>
 */
class HealthEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => fake()->randomElement(['astronot2', 'astronot1', 'astronot3', 'astronot4']),
            'category' => fake()->randomElement(HealthEvent::CATEGORIES),
            'severity' => fake()->randomElement(HealthEvent::SEVERITIES),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(12),
            'payload' => null,
        ];
    }

    /**
     * A critical alert event.
     */
    public function critical(): static
    {
        return $this->state(fn (): array => [
            'category' => 'alert',
            'severity' => 'critical',
        ]);
    }
}
