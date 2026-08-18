<?php

namespace Database\Factories;

use App\Models\StockLevel;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => $this->faker->numberBetween(0, 1000),
            'reserved_quantity' => 0,
            'avg_cost' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
