<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word(),
            'code' => strtoupper($this->faker->unique()->bothify('??###')),
            'type' => 'cash',
            'is_active' => true,
        ];
    }
}
