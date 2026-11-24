<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'is_active' => true,
            'access_level' => 1,
            'benefits' => [fake()->sentence()],
            'max_projects' => 5,
            'max_explorer_projects' => 4,
            'max_mentor_projects' => 2,
            'monthly_price_minor' => 2500,
            'quarterly_price_minor' => 7000,
            'trial_days' => 0,
        ];
    }
}
