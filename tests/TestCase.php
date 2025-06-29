<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function authenticatedUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        
        return $user;
    }

    protected function createAuthenticatedApiUser(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        return [
            'user' => $user,
            'token' => $token,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ];
    }

    protected function apiHeaders(string $token = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
