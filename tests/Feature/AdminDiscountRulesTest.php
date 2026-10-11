<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Discounts\DiscountEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDiscountRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_show_and_soft_delete_a_quantity_rule(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/admin/discount-rules', $this->payload());

        $response->assertCreated()->assertJsonPath('data.name', 'Buy more save more');
        $ruleId = $response->json('data.id');
        $this->assertDatabaseHas('discount_rules', ['id' => $ruleId, 'type' => DiscountRule::TYPE_QUANTITY]);

        $this->getJson('/api/admin/discount-rules')->assertOk()->assertJsonFragment([
            'id' => $ruleId,
            'name' => 'Buy more save more',
        ]);
        $this->getJson("/api/admin/discount-rules/{$ruleId}")->assertOk()->assertJsonPath('data.priority', 5);

        $this->patchJson("/api/admin/discount-rules/{$ruleId}", $this->payload(['name' => 'Updated rule']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated rule');

        $this->deleteJson("/api/admin/discount-rules/{$ruleId}")->assertNoContent();
        $this->assertSoftDeleted('discount_rules', ['id' => $ruleId]);
    }

    public function test_quantity_rules_require_a_minimum_quantity(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/discount-rules', $this->payload(['min_quantity' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['min_quantity']);
    }

    public function test_category_rules_require_a_category(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/discount-rules', $this->payload([
                'type' => DiscountRule::TYPE_CATEGORY,
                'min_quantity' => null,
                'category_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_percentage_discounts_cannot_exceed_one_hundred(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/discount-rules', $this->payload(['value' => 150]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_end_date_must_be_after_the_start_date(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/discount-rules', $this->payload([
                'starts_at' => now()->addDays(2)->toDateTimeString(),
                'ends_at' => now()->addDay()->toDateTimeString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_guests_and_non_admins_cannot_manage_discount_rules(): void
    {
        $this->getJson('/api/admin/discount-rules')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/admin/discount-rules')->assertForbidden();
        $this->actingAs($user)->postJson('/api/admin/discount-rules', $this->payload())->assertForbidden();
    }

    public function test_options_endpoint_returns_form_options(): void
    {
        Category::factory()->create(['name' => 'Kitchen']);
        Vendor::factory()->create(['name' => 'North Star']);
        Product::factory()->create(['name' => 'Bowl']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/discount-rules/options')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['types', 'discount_types', 'categories', 'vendors', 'products'],
            ])
            ->assertJsonFragment(['name' => 'Kitchen'])
            ->assertJsonFragment(['name' => 'North Star'])
            ->assertJsonFragment(['name' => 'Bowl']);
    }

    public function test_admin_can_toggle_rule_status(): void
    {
        $rule = DiscountRule::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/discount-rules/{$rule->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', DiscountRule::STATUS_INACTIVE);
    }

    public function test_active_rules_are_ordered_by_priority_and_id(): void
    {
        $first = DiscountRule::factory()->create(['priority' => 10, 'is_active' => true]);
        $second = DiscountRule::factory()->create(['priority' => 10, 'is_active' => true]);
        $top = DiscountRule::factory()->create(['priority' => 50, 'is_active' => true]);
        DiscountRule::factory()->inactive()->create(['priority' => 99]);
        DiscountRule::factory()->create(['priority' => 99, 'starts_at' => now()->addDay()]);

        $ordered = app(DiscountEngine::class)->activeRules()->pluck('id')->all();

        $this->assertSame([$top->id, $first->id, $second->id], $ordered);
    }

    public function test_test_panel_returns_a_breakdown_without_mutating_data(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($vendor)->create([
            'price' => '20.00',
            'is_active' => true,
        ]);
        $rule = DiscountRule::factory()->category($category)->create([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 25,
        ]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/discount-rules/test', [
                'product_id' => $product->id,
                'vendor_id' => $vendor->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.line_subtotal', '40.00')
            ->assertJsonPath('data.lines.0.discount_amount', '10.00')
            ->assertJsonPath('data.lines.0.applied_rule_name', $rule->name)
            ->assertJsonPath('data.discount_total', '10.00')
            ->assertJsonPath('data.total', '30.00');

        $this->assertDatabaseCount('discount_rules', 1);
        $this->assertDatabaseCount('product_vendor', 1);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('20.00', $product->fresh()->lowest_price);
    }

    public function test_category_cannot_be_deleted_while_a_discount_rule_references_it(): void
    {
        $category = Category::factory()->create();
        DiscountRule::factory()->category($category)->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Buy more save more',
            'description' => 'Spend on volume and save.',
            'type' => DiscountRule::TYPE_QUANTITY,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
            'min_quantity' => 3,
            'priority' => 5,
            'is_active' => true,
        ], $overrides);
    }
}
