<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_dashboard(): void
    {
        foreach ([
            '/admin', '/admin/vendors', '/admin/vendors/create', '/admin/vendors/1', '/admin/vendors/1/edit',
            '/admin/products', '/admin/products/create', '/admin/products/1/edit',
        ] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_non_admin_is_forbidden_from_admin_dashboard(): void
    {
        $user = User::factory()->create();

        foreach ([
            '/admin', '/admin/vendors', '/admin/vendors/create', '/admin/vendors/1', '/admin/vendors/1/edit',
            '/admin/products', '/admin/products/create', '/admin/products/1/edit',
        ] as $path) {
            $this->actingAs($user)->get($path)->assertForbidden();
        }

        $this->actingAs($user)->getJson('/api/admin/vendors')->assertForbidden();
    }

    public function test_admin_can_open_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertViewIs('admin-app');

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.vendors', 0);

        $this->actingAs($admin)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.is_admin', true);
    }
}
