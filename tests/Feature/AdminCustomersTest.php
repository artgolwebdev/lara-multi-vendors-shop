<?php

namespace Tests\Feature;

use App\Models\DiscountRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_only_customers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false, 'name' => 'Customer One']);
        User::factory()->create(['is_admin' => false, 'name' => 'Customer Two']);
        User::factory()->create(['is_admin' => true, 'name' => 'Another Admin']);

        $response = $this->actingAs($admin)->getJson('/api/admin/customers');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_admin_can_search_customers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false, 'name' => 'Alice Smith', 'email' => 'alice@example.com']);
        User::factory()->create(['is_admin' => false, 'name' => 'Bob Jones', 'email' => 'bob@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/admin/customers?search=alice');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Alice Smith', $data[0]['name']);
    }

    public function test_admin_can_grant_and_revoke_loyalty_rule(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);
        $rule = DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_SELECTED_CUSTOMERS,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/customers/{$customer->id}/loyalty-rules/{$rule->id}");
        $response->assertStatus(200);
        $this->assertTrue($customer->fresh()->loyaltyDiscountRules->contains('id', $rule->id));

        $response = $this->actingAs($admin)->deleteJson("/api/admin/customers/{$customer->id}/loyalty-rules/{$rule->id}");
        $response->assertStatus(200);
        $this->assertFalse($customer->fresh()->loyaltyDiscountRules->contains('id', $rule->id));
    }
}
