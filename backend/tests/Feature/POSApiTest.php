<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\RegisterSession;
use App\Models\SerialNumber;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class POSApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;

    protected $branch;

    protected $user;

    protected $warehouse;

    protected $register;

    protected $session;

    protected $customer;

    protected $paymentMethod;

    protected $products;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => true]);
        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->register = CashRegister::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->session = RegisterSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cash_register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Unit::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->products = Product::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable' => true,
            'track_inventory' => true,
            'selling_price' => 100.00,
        ]);

        foreach ($this->products as $product) {
            StockLevel::factory()->create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 50,
            ]);
        }
    }

    /** @test */
    public function user_can_list_pos_products()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/pos/products');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    /** @test */
    public function user_can_find_product_by_barcode()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();
        $product->update(['barcode' => 'TESTBARCODE123']);

        $response = $this->getJson('/api/v1/pos/product/TESTBARCODE123');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id);
    }

    /** @test */
    public function user_can_create_pos_sale()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        $saleData = [
            'register_session_id' => $this->session->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'tax_amount' => 20.00,
                    'tax_percent' => 10,
                ],
            ],
            'total_amount' => 220.00,
            'paid_amount' => 250.00,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_amount' => 250.00,
            'change_amount' => 30.00,
            'tax_amount' => 20.00,
        ];

        $response = $this->postJson('/api/v1/pos/sale', $saleData);

        // This is expected to fail currently due to POSService bugs
        $response->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'register_session_id' => $this->session->id,
            'total' => 220.00,
        ]);
    }

    /** @test */
    public function user_can_create_pos_sale_with_different_unit()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        // Create an alternative unit (e.g. Box = 10 pieces)
        $boxUnit = Unit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Box']);
        ProductUnit::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'unit_id' => $boxUnit->id,
            'conversion_factor' => 10,
            'selling_price' => 900.00, // discount for buying a box
            'barcode' => 'BOXBARCODE123',
            'is_sale_unit' => true,
        ]);

        $saleData = [
            'register_session_id' => $this->session->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $boxUnit->id,
                    'quantity' => 2,
                    'unit_price' => 900.00, // Must match the box selling_price
                    'tax_amount' => 180.00,
                    'tax_percent' => 10,
                ],
            ],
            'total_amount' => 1980.00,
            'paid_amount' => 2000.00,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_amount' => 2000.00,
            'change_amount' => 20.00,
            'tax_amount' => 180.00,
        ];

        $response = $this->postJson('/api/v1/pos/sale', $saleData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'register_session_id' => $this->session->id,
            'total' => 1980.00,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'unit_id' => $boxUnit->id,
            'quantity' => 2,
            'base_quantity' => 20, // 2 boxes * 10 pieces/box
            'conversion_factor' => 10,
            'unit_price' => 900.00,
        ]);
    }

    /** @test */
    public function user_can_parse_dynamic_scale_barcode()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();
        // Give the product a specific 5-digit SKU that matches the barcode
        $product->update(['sku' => '12345']);

        // Barcode format: Prefix(20) + ItemCode(12345) + Weight/Price(01250) + CheckDigit(C)
        // 01250 = 1.250 kg
        $dynamicBarcode = '2012345012503';

        $response = $this->getJson("/api/v1/pos/product/{$dynamicBarcode}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.dynamic_quantity', 1.250);
    }

    /** @test */
    public function user_can_create_sale_with_batch_specific_price()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        // Create a batch with a highly discounted selling_price = 50.00 (base is 100)
        $batch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_remaining' => 100,
            'selling_price' => 50.00,
            'status' => 'active',
        ]);

        $saleData = [
            'register_session_id' => $this->session->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'quantity' => 2, // 2 units at $50 each
                    'unit_price' => 50.00,
                    'tax_amount' => 10.00,
                    'tax_percent' => 10,
                ],
            ],
            'total_amount' => 110.00,
            'paid_amount' => 110.00,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_amount' => 110.00,
            'change_amount' => 0.00,
            'tax_amount' => 10.00,
        ];

        $response = $this->postJson('/api/v1/pos/sale', $saleData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'register_session_id' => $this->session->id,
            'total' => 110.00,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 2,
            'unit_price' => 50.00,
        ]);

        // Assert the batch inventory went down by 2
        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'quantity_remaining' => 98,
        ]);
    }

    /** @test */
    public function user_can_create_sale_with_batch_and_serial_tracking()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        $batch = Batch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_remaining' => 10,
            'status' => 'active',
        ]);

        $serial = SerialNumber::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'serial_number' => 'SN-001',
            'status' => 'in_stock',
            'created_by' => $this->user->id,
        ]);

        $saleData = [
            'register_session_id' => $this->session->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'serial_number_id' => $serial->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'tax_amount' => 10.00,
                    'tax_percent' => 10,
                ],
            ],
            'total_amount' => 110.00,
            'paid_amount' => 110.00,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_amount' => 110.00,
            'change_amount' => 0.00,
            'tax_amount' => 10.00,
        ];

        $response = $this->postJson('/api/v1/pos/sale', $saleData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'serial_number_id' => $serial->id,
            'quantity' => 1,
        ]);

        $this->assertDatabaseHas('serial_numbers', [
            'id' => $serial->id,
            'status' => 'sold',
        ]);
    }

    /** @test */
    public function cashier_can_open_a_register_session_with_cash_register_permission()
    {
        $cashier = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier);

        $response = $this->postJson('/api/v1/register-sessions', [
            'cash_register_id' => $this->register->id,
            'opening_cash' => 100.00,
            'notes' => 'Opening session from cashier test',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('register_sessions', [
            'cash_register_id' => $this->register->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $cashier->id,
            'status' => 'open',
        ]);
    }
}
