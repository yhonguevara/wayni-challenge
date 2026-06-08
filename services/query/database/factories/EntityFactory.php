<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_code' => $this->faker->unique()->numerify('#####'),
            'total_loan_amount' => $this->faker->randomFloat(2, 0, 5000000),
        ];
    }
}
