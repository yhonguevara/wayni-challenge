<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Debtor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Debtor>
 */
class DebtorFactory extends Factory
{
    protected $model = Debtor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identification_number' => $this->faker->unique()->numerify('###########'),
            'max_situation' => $this->faker->randomElement(['01', '03', '04', '05', '11', '21', '23']),
            'total_loan_amount' => $this->faker->randomFloat(2, 0, 1000000),
        ];
    }
}
