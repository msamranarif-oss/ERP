<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Branch;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $branch;
    protected $user;
    protected $categories;

    protected function setUp(): void
    {
        parent::setUp();
        
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
        
        // Create some categories for testing
        $this->categories = Category::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function authenticated_user_can_get_categories()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'is_active',
                        'tenant_id'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ])
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function unauthenticated_user_cannot_get_categories()
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_create_category()
    {
        Sanctum::actingAs($this->user);

        $categoryData = [
            'name' => 'Electronics',
            'description' => 'Electronic devices and accessories',
            'is_active' => true
        ];

        $response = $this->postJson('/api/v1/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'is_active',
                    'tenant_id'
                ]
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Electronics',
                    'description' => 'Electronic devices and accessories',
                    'is_active' => true,
                    'tenant_id' => $this->tenant->id
                ]
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function category_creation_requires_name()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/categories', [
            'description' => 'Test description'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_get_specific_category()
    {
        Sanctum::actingAs($this->user);
        $category = $this->categories->first();

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'is_active',
                    'tenant_id'
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name
                ]
            ]);
    }

    /** @test */
    public function user_can_update_category()
    {
        Sanctum::actingAs($this->user);
        $category = $this->categories->first();

        $updateData = [
            'name' => 'Updated Category Name',
            'description' => 'Updated description',
            'is_active' => false
        ];

        $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Category Name',
                    'description' => 'Updated description',
                    'is_active' => false
                ]
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category Name',
            'description' => 'Updated description',
            'is_active' => false
        ]);
    }

    /** @test */
    public function user_can_delete_category()
    {
        Sanctum::actingAs($this->user);
        $category = $this->categories->first();

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id
        ]);
    }

    /** @test */
    public function cannot_delete_category_with_products()
    {
        Sanctum::actingAs($this->user);
        $category = $this->categories->first();
        
        // Create a product associated with this category
        \App\Models\Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete category with children or products'
            ]);
    }

    /** @test */
    public function categories_are_scoped_to_tenant()
    {
        Sanctum::actingAs($this->user);
        
        // Create another tenant with categories
        $otherTenant = Tenant::factory()->create(['status' => true]);
        $otherCategories = Category::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data'); // Should only see original tenant's categories

        // Verify we don't see other tenant's categories
        $categoryIds = collect($response->json('data'))->pluck('id');
        $otherCategoryIds = $otherCategories->pluck('id');
        
        foreach ($otherCategoryIds as $id) {
            $this->assertFalse($categoryIds->contains($id));
        }
    }

    /** @test */
    public function cannot_access_other_tenant_categories()
    {
        Sanctum::actingAs($this->user);
        $otherTenant = Tenant::factory()->create(['status' => true]);
        $otherCategory = Category::factory()->create([
            'tenant_id' => $otherTenant->id
        ]);

        $response = $this->getJson("/api/v1/categories/{$otherCategory->id}");

        $response->assertStatus(404);
    }
}