<?php

namespace App\Observers;

use App\Models\Tenant;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\Log;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        try {
            $seeder = new ChartOfAccountsSeeder;
            $seeder->seedForTenant($tenant->id);
            Log::info('COA seeded for new tenant.', ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name]);
        } catch (\Exception $e) {
            Log::error('Failed to seed COA for new tenant.', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
