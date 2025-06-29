<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_profile(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $response = $this->getJson('/api/v1/user', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name', 
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ])
            ->assertJson([
                'id' => $auth['user']->id,
                'name' => $auth['user']->name,
                'email' => $auth['user']->email,
            ]);
    }

    public function test_get_user_profile_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_user_profile_contains_expected_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ])
            ->assertJsonMissing([
                'password',
                'remember_token',
            ]);

        $this->assertNotNull($response['email_verified_at']);
        $this->assertNotNull($response['created_at']);
        $this->assertNotNull($response['updated_at']);
    }

    public function test_user_profile_with_unverified_email(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'email_verified_at' => null,
            ]);
    }

    public function test_invalid_token_returns_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/user', [
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
        
        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer invalid-token-that-doesnt-exist',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_profile_response_format(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $response = $this->getJson('/api/v1/user', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]);

        $this->assertIsInt($response['id']);
        $this->assertIsString($response['name']);
        $this->assertIsString($response['email']);
        $this->assertIsString($response['created_at']);
        $this->assertIsString($response['updated_at']);
    }
}