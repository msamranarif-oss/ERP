<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'name' => 'Register ' . $this->faker->unique()->numberBetween(1, 100),
            'code' => 'REG-' . $this->faker->unique()->bothify('??##'),
            'is_active' => true,
        ];
    }
}
