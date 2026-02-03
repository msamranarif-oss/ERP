<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Tenant;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Role permissions
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            
            // Inventory permissions
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'delete suppliers',
            'view warehouses',
            'create warehouses',
            'edit warehouses',
            'delete warehouses',
            
            // POS permissions
            'process sales',
            'view sales',
            'create customers',
            'edit customers',
            'delete customers',
            'manage cash register',
            
            // Installment permissions
            'view credit sales',
            'create credit sales',
            'process payments',
            'view installments',
            
            // Accounting permissions
            'view accounts',
            'create accounts',
            'edit accounts',
            'delete accounts',
            'create journal entries',
            'post journal entries',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo([
            'view users',
            'view products',
            'create products',
            'edit products',
            'view categories',
            'create categories',
            'edit categories',
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'process sales',
            'view sales',
            'create customers',
            'edit customers',
            'view credit sales',
            'create credit sales',
            'process payments',
            'view accounts',
            'view reports',
        ]);

        $cashierRole = Role::create(['name' => 'cashier']);
        $cashierRole->givePermissionTo([
            'process sales',
            'view sales',
            'create customers',
            'edit customers',
            'manage cash register',
        ]);

        $accountantRole = Role::create(['name' => 'accountant']);
        $accountantRole->givePermissionTo([
            'view accounts',
            'create accounts',
            'edit accounts',
            'create journal entries',
            'post journal entries',
            'view reports',
        ]);
    }
}