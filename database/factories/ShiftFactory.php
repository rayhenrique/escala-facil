<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => Sector::factory(),
            'category_id' => Category::factory(),
            'user_id' => User::factory(),
            'status' => ShiftStatus::Scheduled,
        ];
    }

    public function uncovered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'status' => ShiftStatus::Uncovered,
        ]);
    }

    public function withoutUser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }

    public function night(): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_time' => '19:00',
            'end_time' => '07:00',
        ]);
    }
}
