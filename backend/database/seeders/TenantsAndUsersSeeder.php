<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Branch;
use App\Models\User;

class TenantsAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Create a default tenant
        $tenant = Tenant::create([
            'name' => 'Demo Company',
            'slug' => 'demo-company',
            'email' => 'admin@democompany.com',
            'phone' => '+1234567890',
            'address' => '123 Business Street, City, Country',
            'status' => true,
        ]);

        // Create a branch for the tenant
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => '123 Business Street, City, Country',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        // Create admin user
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Admin User',
            'email' => 'admin@democompany.com',
            'password' => bcrypt('password'),
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        // Assign admin role
        $admin->assignRole('admin');

        // Create sample manager user
        $manager = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Manager User',
            'email' => 'manager@democompany.com',
            'password' => bcrypt('password'),
            'phone' => '+1234567891',
            'is_active' => true,
        ]);

        $manager->assignRole('manager');

        // Create sample cashier user
        $cashier = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Cashier User',
            'email' => 'cashier@democompany.com',
            'password' => bcrypt('password'),
            'phone' => '+1234567892',
            'is_active' => true,
        ]);

        $cashier->assignRole('cashier');
    }
}