<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word,
            'abbreviation' => $this->faker->lexify('???'),
            'is_active' => true,
        ];
    }
}
