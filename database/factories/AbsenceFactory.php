<?php

namespace Database\Factories;

use App\Enums\AbsenceStatus;
use App\Models\Absence;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absence>
 */
class AbsenceFactory extends Factory
{
    protected $model = Absence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'attachment_path' => null,
            'status' => AbsenceStatus::Pending,
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AbsenceStatus::Acknowledged,
        ]);
    }

    public function withAttachment(string $path = 'absence-attachments/atestado.pdf'): static
    {
        return $this->state(fn (array $attributes): array => [
            'attachment_path' => $path,
        ]);
    }
}
