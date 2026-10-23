<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CategoryService;
use Tests\Feature\FeatureTestCase;

class CategoryManagementTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** Electronics > Computers > Laptops, plus a separate Books root. */
    private function tree(): array
    {
        $electronics = Category::factory()->create(['name' => 'Electronics']);
        $computers = Category::factory()->create(['name' => 'Computers', 'parent_id' => $electronics->id]);
        $laptops = Category::factory()->create(['name' => 'Laptops', 'parent_id' => $computers->id]);
        $books = Category::factory()->create(['name' => 'Books']);

        return [$electronics, $computers, $laptops, $books];
    }

    public function test_the_tree_lists_parents_before_children_with_depth(): void
    {
        [$electronics, $computers, $laptops, $books] = $this->tree();

        $rows = collect(app(CategoryService::class)->tree())
            ->map(fn (array $row) => [$row['category']->name, $row['depth']])
            ->all();

        $this->assertSame([['Books', 0], ['Electronics', 0], ['Computers', 1], ['Laptops', 2]], $rows);

        $this->actingAs($this->admin)->get('/categories')->assertOk()->assertSeeInOrder(['Books', 'Electronics', 'Computers', 'Laptops']);
    }

    public function test_parent_options_are_indented_and_exclude_the_categorys_own_subtree(): void
    {
        [$electronics, $computers, $laptops, $books] = $this->tree();
        $service = app(CategoryService::class);

        $this->assertSame('— — Laptops', $service->options()[$laptops->id]);
        $this->assertSame([$books->id, $electronics->id], array_keys($service->options(withoutSubtreeOf: $computers->id)));
    }

    public function test_an_admin_creates_a_subcategory_with_a_generated_slug(): void
    {
        $parent = Category::factory()->create();

        $this->actingAs($this->admin)
            ->post('/categories', ['name' => 'Gaming Laptops', 'parent_id' => $parent->id])
            ->assertRedirect('/categories');

        $category = Category::where('name', 'Gaming Laptops')->sole();
        $this->assertSame('gaming-laptops', $category->slug);
        $this->assertSame($parent->id, $category->parent_id);
    }

    public function test_slugs_stay_reserved_by_trashed_categories(): void
    {
        Category::factory()->create(['slug' => 'books'])->delete();

        $this->actingAs($this->admin)
            ->post('/categories', ['name' => 'Books'])
            ->assertSessionHasErrors(['slug' => 'A category with this slug already exists.']);
    }

    public function test_a_trashed_category_cannot_be_chosen_as_parent(): void
    {
        $trashed = tap(Category::factory()->create())->delete();

        $this->actingAs($this->admin)
            ->post('/categories', ['name' => 'Child', 'parent_id' => $trashed->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_a_category_can_be_moved_to_another_branch(): void
    {
        [$electronics, $computers, $laptops, $books] = $this->tree();

        $this->actingAs($this->admin)
            ->put("/categories/{$laptops->id}", ['parent_id' => $books->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($books->id, $laptops->refresh()->parent_id);
    }

    public function test_a_category_cannot_be_moved_under_itself_or_its_descendants(): void
    {
        [$electronics, $computers, $laptops] = $this->tree();

        foreach ([$electronics, $computers, $laptops] as $target) {
            $this->actingAs($this->admin)
                ->put("/categories/{$electronics->id}", ['parent_id' => $target->id])
                ->assertSessionHasErrors(['parent_id' => 'A category cannot be moved under itself or one of its subcategories.']);
        }

        $this->assertNull($electronics->refresh()->parent_id);
    }

    public function test_a_category_can_be_moved_back_to_the_top_level(): void
    {
        [, $computers] = $this->tree();

        $this->actingAs($this->admin)
            ->put("/categories/{$computers->id}", ['parent_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($computers->refresh()->parent_id);
    }

    public function test_a_category_with_subcategories_cannot_be_deleted(): void
    {
        [, $computers] = $this->tree();

        $this->actingAs($this->admin)
            ->delete("/categories/{$computers->id}")
            ->assertSessionHasErrors('category');

        $this->assertNotSoftDeleted($computers);
    }

    public function test_deleting_keeps_product_links_and_restore_brings_them_back(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();

        $this->actingAs($this->admin)->delete("/categories/{$category->id}")->assertRedirect('/categories');
        $this->assertSoftDeleted($category);
        $this->assertNull($product->refresh()->category, 'shown as uncategorised');
        $this->assertSame($category->id, $product->category_id);

        $this->actingAs($this->admin)->get('/categories')->assertSee('Trash')->assertSee('Restore');

        $this->actingAs($this->admin)->patch("/categories/{$category->id}/restore")->assertRedirect('/categories');
        $this->assertTrue($product->refresh()->category->is($category));
    }

    public function test_a_child_cannot_be_restored_before_its_parent(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $child->delete();
        $parent->delete();

        $this->actingAs($this->admin)
            ->patch("/categories/{$child->id}/restore")
            ->assertSessionHasErrors('category');
        $this->assertSoftDeleted($child);

        $this->actingAs($this->admin)->patch("/categories/{$parent->id}/restore");
        $this->actingAs($this->admin)->patch("/categories/{$child->id}/restore")->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($child);
    }

    public function test_product_forms_show_the_category_tree(): void
    {
        $this->tree();

        $this->actingAs($this->admin)->get('/products/create')->assertSee('— — Laptops');
    }
}
