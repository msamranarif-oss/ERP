<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Branch;
use Laravel\Sanctum\Sanctum;

class AuthApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $branch;
    protected $user;

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
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'tenant' => ['id', 'name', 'slug'],
                        'branch' => ['id', 'name'],
                        'roles',
                        'permissions'
                    ],
                    'token'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful'
            ]);

        $this->assertNotNull($response->json('data.token'));
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.'
            ]);
    }

    /** @test */
    public function login_fails_with_inactive_user()
    {
        $this->user->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.'
            ]);
    }

    /** @test */
    public function authenticated_user_can_get_their_profile()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'tenant' => ['id', 'name', 'slug', 'settings'],
                    'branch' => ['id', 'name', 'code'],
                    'roles',
                    'permissions',
                    'settings'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->user->id,
                    'email' => $this->user->email
                ]
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_profile()
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_logout()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
    }

    /** @test */
    public function user_can_update_their_profile()
    {
        Sanctum::actingAs($this->user);

        $newData = [
            'name' => 'Updated Name',
            'phone' => '1234567890'
        ];

        $response = $this->putJson('/api/v1/auth/profile', $newData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'phone' => '1234567890'
        ]);
    }

    /** @test */
    public function user_can_change_password()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/v1/auth/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        // Verify password was actually changed
        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    /** @test */
    public function password_change_fails_with_wrong_current_password()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/v1/auth/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(422);
    }
}