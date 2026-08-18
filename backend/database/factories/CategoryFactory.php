<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name = $this->faker->word,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => $this->faker->sentence,
            'is_active' => true,
        ];
    }
}
