<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_use_the_authenticated_api(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/register', [
                'name' => 'Avery Seller',
                'email' => 'avery@example.com',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Avery Seller')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', ['email' => 'avery@example.com']);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.email', 'avery@example.com');
        $this->postJson('/api/logout')->assertNoContent();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_user_can_log_in_and_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'a-secure-password']);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'a-secure-password',
        ])->assertOk()->assertJsonPath('data.email', $user->email);
    }

    public function test_registration_validates_required_and_unique_fields(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
