<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use Laravel\Sanctum\Sanctum;

class ProductApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $branch;
    protected $user;
    protected $category;
    protected $unit;
    protected $products;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        
        // Create tenant and user for testing
        $this->tenant = Tenant::factory()->create(['status' => true]);
        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'password' => bcrypt('password123')
        ]);
        $this->user->assignRole('admin');
        
        // Create supporting data
        $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = \App\Models\Unit::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create some products for testing
        $this->products = Product::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'base_unit_id' => $this->unit->id
        ]);
    }

    /** @test */
    public function authenticated_user_can_get_products()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'sku',
                            'selling_price',
                            'is_active',
                            'tenant_id',
                            'category' => [
                                'id',
                                'name'
                            ]
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ]
                ]
            ])
            ->assertJsonCount(5, 'data.data');
    }

    /** @test */
    public function unauthenticated_user_cannot_get_products()
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_create_product()
    {
        Sanctum::actingAs($this->user);

        $productData = [
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'barcode' => '1234567890123',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->unit->id,
            'description' => 'Test product description',
            'cost_price' => 100.00,
            'selling_price' => 150.00,
            'is_active' => true,
            'is_sellable' => true,
            'track_inventory' => true
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'barcode',
                    'selling_price',
                    'is_active',
                    'tenant_id',
                    'category' => [
                        'id',
                        'name'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Product',
                    'sku' => 'TEST001',
                    'barcode' => '1234567890123',
                    'selling_price' => 150.00,
                    'is_active' => true,
                    'tenant_id' => $this->tenant->id
                ]
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function product_creation_requires_name()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/products', [
            'sku' => 'TEST001',
            'selling_price' => 150.00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function sku_must_be_unique_per_tenant()
    {
        Sanctum::actingAs($this->user);

        // Create a product with a specific SKU
        $existingProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'UNIQUE001'
        ]);

        // Try to create another product with same SKU
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product',
            'sku' => 'UNIQUE001', // Same SKU
            'selling_price' => 150.00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    /** @test */
    public function user_can_get_specific_product()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'selling_price',
                    'is_active',
                    'tenant_id',
                    'category' => [
                        'id',
                        'name'
                    ],
                    'base_unit' => [
                        'id',
                        'name'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name
                ]
            ]);
    }

    /** @test */
    public function user_can_update_product()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        $updateData = [
            'name' => 'Updated Product Name',
            'selling_price' => 200.00,
            'description' => 'Updated description'
        ];

        $response = $this->putJson("/api/v1/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Product Name',
                    'selling_price' => 200.00,
                    'description' => 'Updated description'
                ]
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
            'selling_price' => 200.00,
            'tenant_id' => $this->user->tenant_id,
        ]);
    }

    /** @test */
    public function user_can_delete_product()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        $this->assertSoftDeleted('products', [
            'id' => $product->id
        ]);
    }

    /** @test */
    public function products_are_scoped_to_tenant()
    {
        // Create another tenant with products
        $otherTenant = Tenant::factory()->create(['status' => true]);
        $otherCategory = Category::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUnit = \App\Models\Unit::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherProducts = Product::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'category_id' => $otherCategory->id,
            'base_unit_id' => $otherUnit->id
        ]);

        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data'); // Should only see original tenant's products

        // Verify we don't see other tenant's products
        $productIds = collect($response->json('data.data'))->pluck('id');
        $otherProductIds = $otherProducts->pluck('id');
        
        foreach ($otherProductIds as $id) {
            $this->assertFalse($productIds->contains($id));
        }
    }

    /** @test */
    public function cannot_access_other_tenant_products()
    {
        $otherTenant = Tenant::factory()->create(['status' => true]);
        $otherCategory = Category::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUnit = \App\Models\Unit::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherProduct = Product::factory()->create([
            'tenant_id' => $otherTenant->id,
            'category_id' => $otherCategory->id,
            'base_unit_id' => $otherUnit->id
        ]);

        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/products/{$otherProduct->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function user_can_get_product_stock()
    {
        Sanctum::actingAs($this->user);
        $product = $this->products->first();

        // Create stock levels for the product
        $warehouse = \App\Models\Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        \App\Models\StockLevel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/stock");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'quantity',
                        'warehouse' => [
                            'id',
                            'name'
                        ]
                    ]
                ]
            ]);
    }
}