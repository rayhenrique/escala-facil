<?php

namespace Database\Factories;

use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftSwap>
 *
 * Nota: a definição cria relacionamentos independentes. Para trocas
 * válidas no domínio, informe explicitamente shift/requester/substitute
 * compatíveis (mesma categoria e mesmo setor).
 */
class ShiftSwapFactory extends Factory
{
    protected $model = ShiftSwap::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'requester_id' => User::factory(),
            'substitute_id' => User::factory(),
            'status' => ShiftSwapStatus::PendingProfessional,
        ];
    }

    public function pendingManager(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShiftSwapStatus::PendingManager,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShiftSwapStatus::Approved,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShiftSwapStatus::Rejected,
        ]);
    }
}
