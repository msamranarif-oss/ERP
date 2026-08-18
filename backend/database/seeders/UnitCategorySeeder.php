<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCategory;
use Illuminate\Database\Seeder;

class UnitCategorySeeder extends Seeder
{
    public function run(): void
    {
        // We seed for all existing tenants, but primarily the first one
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping UnitCategorySeeder.');

            return;
        }

        $categories = [
            ['name' => 'Quantity',  'is_system' => true,  'units' => [
                ['name' => 'Piece',       'abbreviation' => 'pcs',   'symbol' => 'pcs',  'conversion_factor' => 1],
                ['name' => 'Dozen',       'abbreviation' => 'doz',   'symbol' => 'doz',  'conversion_factor' => 12],
                ['name' => 'Box',         'abbreviation' => 'box',   'symbol' => 'box',  'conversion_factor' => 1],
                ['name' => 'Pack',        'abbreviation' => 'pack',  'symbol' => 'pack', 'conversion_factor' => 1],
            ]],
            ['name' => 'Weight',    'is_system' => true,  'units' => [
                ['name' => 'Kilogram',    'abbreviation' => 'kg',    'symbol' => 'kg',   'conversion_factor' => 1],
                ['name' => 'Gram',        'abbreviation' => 'g',     'symbol' => 'g',    'conversion_factor' => 0.001],
                ['name' => 'Pound',       'abbreviation' => 'lb',    'symbol' => 'lb',   'conversion_factor' => 0.453592],
            ]],
            ['name' => 'Volume',    'is_system' => true,  'units' => [
                ['name' => 'Litre',       'abbreviation' => 'L',     'symbol' => 'L',    'conversion_factor' => 1],
                ['name' => 'Millilitre',  'abbreviation' => 'mL',    'symbol' => 'mL',   'conversion_factor' => 0.001],
            ]],
            ['name' => 'Length',    'is_system' => true,  'units' => [
                ['name' => 'Metre',       'abbreviation' => 'm',     'symbol' => 'm',    'conversion_factor' => 1],
                ['name' => 'Centimetre',  'abbreviation' => 'cm',    'symbol' => 'cm',   'conversion_factor' => 0.01],
            ]],
            ['name' => 'Area',      'is_system' => false, 'units' => []],
            ['name' => 'Time',      'is_system' => false, 'units' => []],
        ];

        foreach ($tenants as $tenant) {
            foreach ($categories as $catData) {
                $category = UnitCategory::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $catData['name']],
                    ['is_system' => $catData['is_system'], 'is_active' => true]
                );

                foreach ($catData['units'] as $unitData) {
                    Unit::updateOrCreate(
                        ['tenant_id' => $tenant->id, 'abbreviation' => $unitData['abbreviation']],
                        [
                            'unit_category_id' => $category->id,
                            'name' => $unitData['name'],
                            'symbol' => $unitData['symbol'],
                            'conversion_factor' => $unitData['conversion_factor'],
                            'is_system' => true,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $this->command->info('Unit categories and system units seeded.');
    }
}
