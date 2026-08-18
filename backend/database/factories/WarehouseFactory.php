<?php

namespace Database\Factories;

use App\Models\Warehouse;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company . ' Warehouse',
            'code' => $this->faker->unique()->lexify('WH-????'),
            'address' => $this->faker->address,
            'phone' => $this->faker->phoneNumber,
            'is_active' => true,
        ];
    }
}
