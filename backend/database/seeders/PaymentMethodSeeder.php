<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::first()?->id;

        if (!$tenantId) {
            return;
        }

        $methods = [
            [
                'tenant_id' => $tenantId,
                'name' => 'Cash',
                'code' => 'CASH',
                'type' => 'cash',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Credit Card',
                'code' => 'CARD',
                'type' => 'card',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Customer Credit',
                'code' => 'CREDIT',
                'type' => 'credit',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Bank Transfer',
                'code' => 'BANK',
                'type' => 'bank_transfer',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['tenant_id' => $method['tenant_id'], 'code' => $method['code']],
                $method
            );
        }
    }
}
