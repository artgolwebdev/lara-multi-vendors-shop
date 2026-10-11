<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_non_admin_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'is_admin' => false,
        ]);
        $user = User::where('email', 'test@example.com')->first();
        $this->assertFalse($user->is_admin);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_does_not_accept_is_admin(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => true,
        ]);

        $response->assertSessionHasErrors('is_admin');
        $this->assertDatabaseMissing('users', ['email' => 'test2@example.com']);
    }

    public function test_validation_errors_on_invalid_registration(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'invalid',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);
        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }
}
