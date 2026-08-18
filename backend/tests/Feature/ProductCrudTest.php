<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;
    protected Unit $unit;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user with tenant
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        
        // Create test data
        $this->category = Category::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $this->unit = Unit::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $this->brand = Brand::factory()->create(['tenant_id' => $this->user->tenant_id]);
    }

    /** @test */
    public function can_create_product_with_all_fields()
    {
        $productData = [
            'name' => 'Premium Wireless Mouse',
            'sku' => 'ELEC-001',
            'barcode' => '1234567890123',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'base_unit_id' => $this->unit->id,
            'description' => 'High-quality wireless mouse with ergonomic design',
            'short_description' => 'Premium wireless mouse',
            'internal_notes' => 'Top seller in electronics category',
            'cost_price' => 25.00,
            'selling_price' => 49.99,
            'min_price' => 45.00,
            'wholesale_price' => 40.00,
            'max_price' => 59.99,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'min_order_qty' => 1,
            'max_order_qty' => 100,
            'is_active' => true,
            'is_featured' => false,
            'is_pos_visible' => true,
            'is_online_visible' => true,
            'is_sellable' => true,
            'is_purchasable' => true,
            'track_inventory' => true,
            'has_variants' => false,
            'allow_negative_stock' => false,
            'batch_tracking' => false,
            'serial_tracking' => false,
            'lot_tracking' => false,
            'tax_type' => 'exclusive',
            'tax_rate' => 10.00,
            'valuation_method' => 'avg_cost',
            'product_type' => 'simple',
            'status' => 'active',
            'weight' => 0.150,
            'length' => 12.500,
            'width' => 7.500,
            'height' => 4.000,
            'warranty_period' => '1 Year',
            'warranty_terms' => 'Manufacturer warranty covers defects',
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product created successfully',
            ])
            ->assertJsonFragment(['name' => 'Premium Wireless Mouse'])
            ->assertJsonFragment(['sku' => 'ELEC-001']);

        $this->assertDatabaseHas('products', [
            'sku' => 'ELEC-001',
            'name' => 'Premium Wireless Mouse',
            'tenant_id' => $this->user->tenant_id,
        ]);
    }

    /** @test */
    public function can_create_product_without_sku_and_it_generates_automatically()
    {
        $productData = [
            'name' => 'Test Product Auto SKU',
            'selling_price' => 29.99,
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('products', [
            'name' => 'Test Product Auto SKU',
            'tenant_id' => $this->user->tenant_id,
        ]);

        // Verify SKU was generated
        $product = Product::where('name', 'Test Product Auto SKU')->first();
        $this->assertNotNull($product->sku);
        $this->assertStringStartsWith('TES-', $product->sku);
    }

    /** @test */
    public function cannot_create_product_with_duplicate_sku()
    {
        // Create existing product
        Product::factory()->create([
            'sku' => 'DUPLICATE-001',
            'tenant_id' => $this->user->tenant_id,
        ]);

        $productData = [
            'name' => 'Another Product',
            'sku' => 'DUPLICATE-001',
            'selling_price' => 19.99,
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "A product with SKU 'DUPLICATE-001' already exists",
                'error_code' => 'SKU_TAKEN',
            ]);
    }

    /** @test */
    public function cannot_create_product_with_duplicate_barcode()
    {
        // Create existing product
        Product::factory()->create([
            'barcode' => '9999999999999',
            'tenant_id' => $this->user->tenant_id,
        ]);

        $productData = [
            'name' => 'Another Product',
            'barcode' => '9999999999999',
            'selling_price' => 19.99,
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "A product with barcode '9999999999999' already exists",
                'error_code' => 'BARCODE_TAKEN',
            ]);
    }

    /** @test */
    public function can_list_products_with_pagination()
    {
        // Create multiple products
        Product::factory()->count(25)->create(['tenant_id' => $this->user->tenant_id]);

        $response = $this->getJson('/api/v1/products?per_page=15');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [],
                    'links',
                    'meta',
                ],
            ]);

        $responseData = $response->json('data');
        $this->assertCount(15, $responseData['data']);
        $this->assertEquals(25, $responseData['meta']['total']);
    }

    /** @test */
    public function can_search_products_by_name()
    {
        Product::factory()->create([
            'name' => 'Wireless Bluetooth Headphones',
            'tenant_id' => $this->user->tenant_id,
        ]);
        
        Product::factory()->create([
            'name' => 'Wired Gaming Mouse',
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->getJson('/api/v1/products?search=wireless');

        $response->assertStatus(200);
        
        $products = $response->json('data.data');
        $this->assertCount(1, $products);
        $this->assertEquals('Wireless Bluetooth Headphones', $products[0]['name']);
    }

    /** @test */
    public function can_search_products_by_sku()
    {
        Product::factory()->create([
            'sku' => 'SEARCH-001',
            'name' => 'Search Test Product',
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->getJson('/api/v1/products?search=SEARCH-001');

        $response->assertStatus(200);
        
        $products = $response->json('data.data');
        $this->assertCount(1, $products);
        $this->assertEquals('SEARCH-001', $products[0]['sku']);
    }

    /** @test */
    public function can_filter_products_by_category()
    {
        $category1 = Category::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $category2 = Category::factory()->create(['tenant_id' => $this->user->tenant_id]);

        Product::factory()->count(3)->create([
            'category_id' => $category1->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        Product::factory()->count(2)->create([
            'category_id' => $category2->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->getJson("/api/v1/products?category_id={$category1->id}");

        $response->assertStatus(200);
        
        $products = $response->json('data.data');
        $this->assertCount(3, $products);
        foreach ($products as $product) {
            $this->assertEquals($category1->id, $product['category']['id']);
        }
    }

    /** @test */
    public function can_filter_products_by_brand()
    {
        $brand1 = Brand::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $brand2 = Brand::factory()->create(['tenant_id' => $this->user->tenant_id]);

        Product::factory()->count(5)->create([
            'brand_id' => $brand1->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        Product::factory()->count(3)->create([
            'brand_id' => $brand2->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->getJson("/api/v1/products?brand_id={$brand1->id}");

        $response->assertStatus(200);
        
        $products = $response->json('data.data');
        $this->assertCount(5, $products);
    }

    /** @test */
    public function can_filter_active_products()
    {
        Product::factory()->count(10)->create([
            'is_active' => true,
            'tenant_id' => $this->user->tenant_id,
        ]);

        Product::factory()->count(5)->create([
            'is_active' => false,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->getJson('/api/v1/products?is_active=true');

        $response->assertStatus(200);
        
        $products = $response->json('data.data');
        $this->assertCount(10, $products);
        foreach ($products as $product) {
            $this->assertTrue($product['is_active']);
        }
    }

    /** @test */
    public function can_get_single_product_with_relationships()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'base_unit_id' => $this->unit->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                ],
            ]);

        // Verify relationships are loaded
        $responseData = $response->json('data');
        $this->assertArrayHasKey('category', $responseData);
        $this->assertArrayHasKey('brand', $responseData);
        $this->assertArrayHasKey('base_unit', $responseData);
    }

    /** @test */
    public function can_update_product()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Original Name',
            'selling_price' => 19.99,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'selling_price' => 29.99,
            'is_featured' => true,
        ];

        $response = $this->putJson("/api/v1/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product updated successfully',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'selling_price' => 29.99,
            'is_featured' => true,
        ]);
    }

    /** @test */
    public function cannot_update_product_with_duplicate_sku()
    {
        $product1 = Product::factory()->create([
            'sku' => 'PROD-001',
            'tenant_id' => $this->user->tenant_id,
        ]);

        $product2 = Product::factory()->create([
            'sku' => 'PROD-002',
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->putJson("/api/v1/products/{$product1->id}", [
            'sku' => 'PROD-002', // Try to use product2's SKU
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "This SKU code is already in use by another product.",
                'error_code' => 'SKU_TAKEN',
            ]);
    }

    /** @test */
    public function can_delete_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->user->tenant_id]);

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
    public function cannot_delete_nonexistent_product()
    {
        $response = $this->deleteJson('/api/v1/products/999999');

        $response->assertStatus(404);
    }

    /** @test */
    public function validation_fails_for_invalid_price()
    {
        $productData = [
            'name' => 'Invalid Product',
            'selling_price' => -10.00, // Negative price
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selling_price');
    }

    /** @test */
    public function validation_fails_for_invalid_tax_rate()
    {
        $productData = [
            'name' => 'Invalid Product',
            'selling_price' => 10.00,
            'tax_rate' => 150, // Tax rate > 100
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('tax_rate');
    }

    /** @test */
    public function validation_fails_for_missing_required_name()
    {
        $productData = [
            'selling_price' => 10.00,
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /** @test */
    public function can_sort_products_by_different_fields()
    {
        Product::factory()->create(['name' => 'Zebra Product', 'tenant_id' => $this->user->tenant_id]);
        Product::factory()->create(['name' => 'Apple Product', 'tenant_id' => $this->user->tenant_id]);
        Product::factory()->create(['name' => 'Mango Product', 'tenant_id' => $this->user->tenant_id]);

        // Sort by name ascending
        $response = $this->getJson('/api/v1/products?sort_by=name&sort_dir=asc');
        $products = $response->json('data.data');
        $this->assertEquals('Apple Product', $products[0]['name']);
        $this->assertEquals('Mango Product', $products[1]['name']);
        $this->assertEquals('Zebra Product', $products[2]['name']);

        // Sort by name descending
        $response = $this->getJson('/api/v1/products?sort_by=name&sort_dir=desc');
        $products = $response->json('data.data');
        $this->assertEquals('Zebra Product', $products[0]['name']);
        $this->assertEquals('Apple Product', $products[2]['name']);
    }
}
