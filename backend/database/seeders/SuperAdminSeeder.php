<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Branch;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure the 'super-admin' role exists with ALL permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        // Also ensure the regular 'admin' role has all permissions
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(Permission::all());
        }

        // Get the first tenant and branch (or create them if they don't exist)
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Main Company',
                'slug' => 'main-company',
                'email' => 'admin@company.com',
                'status' => true,
            ]);
        }

        $branch = Branch::where('tenant_id', $tenant->id)->first();
        if (!$branch) {
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'is_active' => true,
            ]);
        }

        // Create or update the super admin user
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@erp.com'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => 'Super Admin',
                'password' => bcrypt('admin123'),
                'phone' => '+1000000000',
                'is_active' => true,
            ]
        );

        // Assign super-admin AND admin roles
        $superAdmin->syncRoles(['super-admin', 'admin']);

        $this->command->info('');
        $this->command->info('╔══════════════════════════════════════════╗');
        $this->command->info('║       SUPER ADMIN CREATED SUCCESSFULLY  ║');
        $this->command->info('╠══════════════════════════════════════════╣');
        $this->command->info('║  Email:    superadmin@erp.com           ║');
        $this->command->info('║  Password: admin123                     ║');
        $this->command->info('║  Role:     super-admin (ALL permissions)║');
        $this->command->info('╚══════════════════════════════════════════╝');
        $this->command->info('');
    }
}
