<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Branch;
use Laravel\Sanctum\Sanctum;

class UserApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $branch;
    protected $adminUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant and users for testing
        $this->tenant = Tenant::factory()->create(['status' => true]);
        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create admin user
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'password' => bcrypt('password123')
        ]);
        $this->adminUser->assignRole('admin');
        
        // Create regular user
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'password' => bcrypt('password123')
        ]);
        $this->regularUser->assignRole('cashier');
    }

    /** @test */
    public function admin_can_get_all_users()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'tenant_id'
                    ]
                ]
            ]);
    }

    /** @test */
    public function regular_user_cannot_get_all_users()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_user()
    {
        Sanctum::actingAs($this->adminUser);

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890',
            'is_active' => true
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'is_active'
                ]
            ])
            ->assertJson([
                'data' => [
                    'name' => 'New User',
                    'email' => 'newuser@example.com',
                    'is_active' => true
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function user_creation_requires_valid_email()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_creation_requires_password_confirmation()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123'
            // Missing password_confirmation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function admin_can_get_specific_user()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/v1/users/{$this->regularUser->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'is_active',
                    'tenant' => [
                        'id',
                        'name'
                    ],
                    'branch' => [
                        'id',
                        'name'
                    ]
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $this->regularUser->id,
                    'name' => $this->regularUser->name,
                    'email' => $this->regularUser->email
                ]
            ]);
    }

    /** @test */
    public function admin_can_update_user()
    {
        Sanctum::actingAs($this->adminUser);

        $updateData = [
            'name' => 'Updated User Name',
            'phone' => '0987654321',
            'is_active' => false
        ];

        $response = $this->putJson("/api/v1/users/{$this->regularUser->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated User Name',
                    'phone' => '0987654321',
                    'is_active' => false
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'name' => 'Updated User Name',
            'phone' => '0987654321',
            'is_active' => false
        ]);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/v1/users/{$this->regularUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $this->regularUser->id
        ]);
    }

    /** @test */
    public function users_are_scoped_to_tenant()
    {
        Sanctum::actingAs($this->adminUser);
        
        // Create another tenant with users
        $otherTenant = Tenant::factory()->create(['status' => true]);
        $otherBranch = Branch::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUsers = User::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherBranch->id
        ]);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200);
        
        // Verify we only see users from our tenant
        $responseData = $response->json();
        $userIds = collect($responseData['data'])->pluck('id');
        $otherUserIds = $otherUsers->pluck('id');
        
        foreach ($otherUserIds as $id) {
            $this->assertFalse($userIds->contains($id));
        }
        
        // Should see our tenant users plus the admin user we created
        $this->assertEquals(2, count($responseData['data'])); // adminUser + regularUser
    }

    /** @test */
    public function cannot_access_other_tenant_users()
    {
        Sanctum::actingAs($this->adminUser);
        
        $otherTenant = Tenant::factory()->create(['status' => true]);
        $otherBranch = Branch::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherBranch->id
        ]);

        $response = $this->getJson("/api/v1/users/{$otherUser->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function user_cannot_update_other_tenant_user()
    {
        Sanctum::actingAs($this->adminUser);
        
        $otherTenant = Tenant::factory()->create(['status' => true]);
        $otherBranch = Branch::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherBranch->id
        ]);

        $response = $this->putJson("/api/v1/users/{$otherUser->id}", [
            'name' => 'Hacked Name'
        ]);

        $response->assertStatus(404);
        
        $this->assertDatabaseMissing('users', [
            'id' => $otherUser->id,
            'name' => 'Hacked Name'
        ]);
    }
}