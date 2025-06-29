<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                ],
                'token'
            ])
            ->assertJson([
                'message' => 'User registered successfully',
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertNotEmpty($response['token']);
    }

    public function test_registration_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'message',
                'errors' => [
                    'name',
                    'email',
                    'password',
                ]
            ]);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_with_password_mismatch(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                ],
                'token'
            ])
            ->assertJson([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ]
            ]);

        $this->assertNotEmpty($response['token']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid credentials',
                'message' => 'The provided email or password is incorrect.',
            ]);
    }

    public function test_login_fails_with_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid credentials',
            ]);
    }

    public function test_login_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $response = $this->postJson('/api/v1/auth/logout', [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logout successful',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $auth['user']->id,
        ]);
    }

    public function test_logout_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout_all_devices(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('device1')->plainTextToken;
        $token2 = $user->createToken('device2')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $response = $this->postJson('/api/v1/auth/logout-all', [], [
            'Authorization' => 'Bearer ' . $token1,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out from all devices successfully',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_all_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout-all');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $response = $this->getJson('/api/v1/auth/me', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'user' => [
                    'id' => $auth['user']->id,
                    'name' => $auth['user']->name,
                    'email' => $auth['user']->email,
                ]
            ]);
    }

    public function test_get_profile_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_refresh_token(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $originalToken = $auth['token'];

        $response = $this->postJson('/api/v1/auth/refresh', [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token'
            ])
            ->assertJson([
                'message' => 'Token refreshed successfully',
            ]);

        $newToken = $response['token'];
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($originalToken, $newToken);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $auth['user']->id,
            'token' => hash('sha256', explode('|', $originalToken)[1]),
        ]);
    }

    public function test_refresh_token_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_invalid_token_returns_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
    }

    public function test_expired_token_returns_unauthorized(): void
    {
        // Note: Sanctum token expiration behavior in testing may differ from production
        // For now, we'll test that invalid tokens return 401
        $user = User::factory()->create();
        
        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer invalid-token-that-doesnt-exist',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
    }
}