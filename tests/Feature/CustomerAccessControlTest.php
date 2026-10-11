<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_routes(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $response = $this->actingAs($customer)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_guest_redirected_from_protected_admin(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_account_api(): void
    {
        $response = $this->getJson('/api/account/profile');
        $response->assertStatus(401);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
    }
}
