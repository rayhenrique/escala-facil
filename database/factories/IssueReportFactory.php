<?php

namespace Database\Factories;

use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueReport>
 */
class IssueReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'protocol' => 'SUP-'.$this->faker->date('Ymd').'-'.$this->faker->unique()->regexify('[A-Z0-9]{4}'),
            'user_id' => User::factory(),
            'module' => 'trocas',
            'description' => $this->faker->sentence(12),
            'expected_behavior' => $this->faker->sentence(10),
            'attachment_path' => null,
        ];
    }
}
