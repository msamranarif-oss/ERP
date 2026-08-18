<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\AccountType;
use App\Models\ChartOfAccount;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = \App\Models\Tenant::first()?->id;

        if (!$tenantId) {
            return;
        }

        // Create default categories
        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Electronic devices and accessories'],
            ['name' => 'Clothing', 'slug' => 'clothing', 'description' => 'Apparel and fashion items'],
            ['name' => 'Food & Beverages', 'slug' => 'food-beverages', 'description' => 'Food products and beverages'],
            ['name' => 'Home & Garden', 'slug' => 'home-garden', 'description' => 'Home improvement and garden supplies'],
            ['name' => 'Books & Media', 'slug' => 'books-media', 'description' => 'Books, magazines, and media products'],
        ];

        foreach ($categories as $categoryData) {
            $categoryData['tenant_id'] = $tenantId;
            Category::create($categoryData);
        }

        // Create default units
        $units = [
            ['name' => 'Piece', 'abbreviation' => 'pcs', 'is_base' => true],
            ['name' => 'Kilogram', 'abbreviation' => 'kg', 'is_base' => false],
            ['name' => 'Gram', 'abbreviation' => 'g', 'is_base' => false],
            ['name' => 'Liter', 'abbreviation' => 'L', 'is_base' => false],
            ['name' => 'Meter', 'abbreviation' => 'm', 'is_base' => false],
            ['name' => 'Box', 'abbreviation' => 'box', 'is_base' => false],
            ['name' => 'Pack', 'abbreviation' => 'pack', 'is_base' => false],
        ];

        foreach ($units as $unitData) {
            $unitData['tenant_id'] = $tenantId;
            Unit::create($unitData);
        }

        // Create default warehouse
        Warehouse::create([
            'tenant_id' => $tenantId,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address' => 'Warehouse Address, City, Country',
            'phone' => '+1234567890',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Create default suppliers
        $suppliers = [
            [
                'name' => 'Tech Supplies Inc.',
                'code' => 'TSI001',
                'email' => 'info@techsupplies.com',
                'phone' => '+1234567890',
                'address' => '123 Tech Street, City, Country',
                'city' => 'Tech City',
                'country' => 'Country',
                'contact_person' => 'John Smith',
                'is_active' => true,
            ],
            [
                'name' => 'Fashion Wholesale Co.',
                'code' => 'FWC001',
                'email' => 'orders@fashionwholesale.com',
                'phone' => '+1234567891',
                'address' => '456 Fashion Avenue, City, Country',
                'city' => 'Fashion City',
                'country' => 'Country',
                'contact_person' => 'Jane Doe',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplierData) {
            $supplierData['tenant_id'] = $tenantId;
            Supplier::create($supplierData);
        }

        // Create default account types
        $accountTypes = [
            ['name' => 'Current Asset', 'category' => 'asset', 'normal_balance' => 'debit'],
            ['name' => 'Fixed Asset', 'category' => 'asset', 'normal_balance' => 'debit'],
            ['name' => 'Current Liability', 'category' => 'liability', 'normal_balance' => 'credit'],
            ['name' => 'Long-term Liability', 'category' => 'liability', 'normal_balance' => 'credit'],
            ['name' => 'Equity', 'category' => 'equity', 'normal_balance' => 'credit'],
            ['name' => 'Revenue', 'category' => 'revenue', 'normal_balance' => 'credit'],
            ['name' => 'Operating Expense', 'category' => 'expense', 'normal_balance' => 'debit'],
            ['name' => 'Non-operating Expense', 'category' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($accountTypes as $typeData) {
            AccountType::create($typeData);
        }

        // Create sample products
        $categories = Category::where('tenant_id', $tenantId)->get();
        $units = Unit::where('tenant_id', $tenantId)->get();
        
        if (!$categories->isEmpty() && !$units->isEmpty()) {
            $sampleProducts = [
                [
                    'name' => 'Smartphone XYZ',
                    'sku' => 'PHONE-XYZ-001',
                    'barcode' => '1234567890123',
                    'description' => 'Latest smartphone model with advanced features',
                    'cost_price' => 300.00,
                    'selling_price' => 500.00,
                    'reorder_level' => 10,
                    'is_active' => true,
                    'is_sellable' => true,
                    'track_inventory' => true,
                    'category_id' => $categories->first()->id,
                    'base_unit_id' => $units->first()->id,
                ],
                [
                    'name' => 'Laptop ABC',
                    'sku' => 'LAPTOP-ABC-001',
                    'barcode' => '1234567890124',
                    'description' => 'High-performance laptop for professionals',
                    'cost_price' => 800.00,
                    'selling_price' => 1200.00,
                    'reorder_level' => 5,
                    'is_active' => true,
                    'is_sellable' => true,
                    'track_inventory' => true,
                    'category_id' => $categories->first()->id,
                    'base_unit_id' => $units->first()->id,
                ],
                [
                    'name' => 'Wireless Headphones',
                    'sku' => 'HEADPHONES-001',
                    'barcode' => '1234567890125',
                    'description' => 'Noise-cancelling wireless headphones',
                    'cost_price' => 50.00,
                    'selling_price' => 100.00,
                    'reorder_level' => 20,
                    'is_active' => true,
                    'is_sellable' => true,
                    'track_inventory' => true,
                    'category_id' => $categories->first()->id,
                    'base_unit_id' => $units->first()->id,
                ],
            ];

            foreach ($sampleProducts as $productData) {
                $productData['tenant_id'] = $tenantId;
                \App\Models\Product::create($productData);
            }
        }
    }
}