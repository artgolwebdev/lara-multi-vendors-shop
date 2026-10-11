<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_list_and_delete_unused_categories(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/admin/categories', ['name' => 'Tableware']);

        $response->assertCreated()->assertJsonPath('data.name', 'Tableware');
        $categoryId = $response->json('data.id');
        $this->assertDatabaseHas('categories', ['id' => $categoryId, 'name' => 'Tableware']);

        $this->getJson('/api/admin/categories')->assertOk()->assertJsonFragment([
            'id' => $categoryId,
            'name' => 'Tableware',
        ]);
        $this->getJson("/api/admin/categories/{$categoryId}")->assertOk()->assertJsonPath('data.name', 'Tableware');
        $this->patchJson("/api/admin/categories/{$categoryId}", ['name' => 'Ceramics'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ceramics');

        $this->deleteJson("/api/admin/categories/{$categoryId}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    public function test_category_name_must_be_unique(): void
    {
        $category = Category::factory()->create(['name' => 'Kitchen']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/categories', ['name' => $category->name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_category_cannot_be_deleted_while_products_reference_it(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Categories assigned to products or discount rules cannot be deleted.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }
}
