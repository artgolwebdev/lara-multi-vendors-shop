<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorefrontPagesTest extends TestCase
{
    /**
     * Every storefront page is the same SPA host; these routes only assert the
     * HTML shell mounts the store React app so deep links work on hard refresh.
     */
    public function test_storefront_routes_render_the_spa_host(): void
    {
        foreach (['/', '/shop', '/categories', '/categories/1', '/products/1', '/cart', '/checkout', '/order-confirmation/1'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertViewIs('store-app')
                ->assertSee('id="store-app"', false);
        }
    }

    public function test_account_route_mounts_the_auth_spa(): void
    {
        $this->get('/account')
            ->assertOk()
            ->assertViewIs('auth-app')
            ->assertSee('id="auth-app"', false);
    }

    public function test_login_and_registration_routes_mount_the_auth_spa(): void
    {
        foreach (['/login', '/register'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertViewIs('auth-app')
                ->assertSee('id="auth-app"', false);
        }
    }

    public function test_login_redirect_preserves_the_original_destination(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertViewHas('intendedUrl', url('/admin'));
    }

    public function test_password_recovery_route_renders_the_branded_form(): void
    {
        $this->get('/password/reset')
            ->assertOk()
            ->assertViewIs('auth.passwords.email')
            ->assertSee('legacy-auth-card', false)
            ->assertSee('Send password reset link');
    }
}
