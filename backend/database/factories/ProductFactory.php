<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => Category::factory(),
            'base_unit_id' => Unit::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->lexify('PROD-????'),
            'barcode' => $this->faker->ean13(),
            'description' => $this->faker->paragraph,
            'cost_price' => $this->faker->randomFloat(2, 50, 500),
            'selling_price' => $this->faker->randomFloat(2, 60, 1000),
            'reorder_level' => 10,
            'is_active' => true,
            'is_sellable' => true,
            'track_inventory' => true,
        ];
    }
}
