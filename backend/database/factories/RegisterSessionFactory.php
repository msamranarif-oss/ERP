<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegisterSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'cash_register_id' => CashRegister::factory(),
            'user_id' => User::factory(),
            'opening_balance' => 0,
            'cash_sales' => 0,
            'card_sales' => 0,
            'other_sales' => 0,
            'refunds' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'expected_balance' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ];
    }
}
