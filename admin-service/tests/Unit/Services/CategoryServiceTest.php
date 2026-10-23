<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class CategoryServiceTest extends UnitTestCase
{
    private CategoryRepository&MockInterface $categories;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categories = $this->mock(CategoryRepository::class);
        $this->service = app(CategoryService::class);
    }

    private function category(int $id, string $name, ?int $parentId = null): Category
    {
        $category = new Category(['name' => $name, 'slug' => Str::slug($name), 'parent_id' => $parentId]);
        $category->id = $id;
        $category->syncOriginal();

        return $category;
    }

    /** The repository returns active categories sorted by name. */
    private function givenActive(Category ...$categories): void
    {
        $this->categories->shouldReceive('allActive')->andReturn(new Collection($categories));
    }

    /** @param  list<array{category: Category, depth: int}>  $tree */
    private function names(array $tree): array
    {
        return array_map(fn (array $row) => [$row['category']->name, $row['depth']], $tree);
    }

    public function test_the_tree_lists_each_parent_before_its_children(): void
    {
        $this->givenActive(
            $this->category(4, 'Books'),
            $this->category(2, 'Computers', parentId: 1),
            $this->category(1, 'Electronics'),
            $this->category(3, 'Laptops', parentId: 2),
        );

        $this->assertSame([['Books', 0], ['Electronics', 0], ['Computers', 1], ['Laptops', 2]], $this->names($this->service->tree()));
    }

    public function test_a_category_whose_parent_is_trashed_is_shown_at_the_top(): void
    {
        $this->givenActive($this->category(3, 'Laptops', parentId: 99));

        $this->assertSame([['Laptops', 0]], $this->names($this->service->tree()));
    }

    public function test_options_are_indented_and_can_leave_out_a_subtree(): void
    {
        $this->givenActive(
            $this->category(4, 'Books'),
            $this->category(2, 'Computers', parentId: 1),
            $this->category(1, 'Electronics'),
            $this->category(3, 'Laptops', parentId: 2),
        );

        $this->assertSame([4 => 'Books', 1 => 'Electronics', 2 => '— Computers', 3 => '— — Laptops'], $this->service->options());
        $this->assertSame([4 => 'Books', 1 => 'Electronics'], $this->service->options(withoutSubtreeOf: 2));
    }

    public function test_create_takes_the_tree_lock_before_checking_the_slug(): void
    {
        $created = $this->category(5, 'Mugs');
        $this->categories->shouldReceive('lockTree')->once()->ordered();
        $this->categories->shouldReceive('existsBySlug')->once()->with('mugs', null)->andReturnFalse()->ordered();
        $this->categories->shouldReceive('create')->once()->with(['name' => 'Mugs', 'slug' => 'mugs'])->andReturn($created)->ordered();

        $this->assertSame($created, $this->service->create(['name' => 'Mugs', 'slug' => 'mugs']));
    }

    public function test_create_refuses_a_slug_that_is_taken(): void
    {
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldReceive('existsBySlug')->andReturnTrue();
        $this->categories->shouldNotReceive('create');

        $this->expectExceptionObject(ValidationException::withMessages(['slug' => 'A category with this slug already exists.']));

        $this->service->create(['name' => 'Mugs', 'slug' => 'mugs']);
    }

    public function test_a_category_cannot_be_moved_into_its_own_subtree(): void
    {
        $electronics = $this->category(1, 'Electronics');
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldReceive('isSelfOrDescendant')->once()->with(3, 1)->andReturnTrue();
        $this->categories->shouldNotReceive('save');

        try {
            $this->service->update($electronics, ['parent_id' => 3]);
            $this->fail('The move was allowed.');
        } catch (ValidationException $e) {
            $this->assertSame(['parent_id' => ['A category cannot be moved under itself or one of its subcategories.']], $e->errors());
        }
    }

    public function test_a_valid_move_is_saved(): void
    {
        $laptops = $this->category(3, 'Laptops', parentId: 2);
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldReceive('isSelfOrDescendant')->once()->with(4, 3)->andReturnFalse();
        $this->categories->shouldReceive('save')->once()->with($laptops);

        $this->assertSame(4, $this->service->update($laptops, ['parent_id' => 4])->parent_id);
    }

    public function test_renaming_skips_the_loop_and_slug_checks(): void
    {
        $laptops = $this->category(3, 'Laptops', parentId: 2);
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldNotReceive('isSelfOrDescendant');
        $this->categories->shouldNotReceive('existsBySlug');
        $this->categories->shouldReceive('save')->once();

        $this->service->update($laptops, ['name' => 'Notebooks', 'parent_id' => 2]);
    }

    public function test_moving_to_the_top_level_needs_no_loop_check(): void
    {
        $laptops = $this->category(3, 'Laptops', parentId: 2);
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldNotReceive('isSelfOrDescendant');
        $this->categories->shouldReceive('save')->once();

        $this->assertNull($this->service->update($laptops, ['parent_id' => null])->parent_id);
    }

    public function test_a_category_with_subcategories_is_not_deleted(): void
    {
        $computers = $this->category(2, 'Computers');
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldReceive('hasActiveChildren')->with($computers)->andReturnTrue();
        $this->categories->shouldNotReceive('delete');

        $this->expectException(ValidationException::class);

        $this->service->delete($computers);
    }

    public function test_a_leaf_category_is_deleted(): void
    {
        $laptops = $this->category(3, 'Laptops');
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldReceive('hasActiveChildren')->andReturnFalse();
        $this->categories->shouldReceive('delete')->once()->with($laptops);

        $this->service->delete($laptops);
    }

    public function test_a_child_is_only_restored_when_its_parent_is_active(): void
    {
        $laptops = $this->category(3, 'Laptops', parentId: 2);
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldReceive('isActive')->with(2)->andReturn(false, true);
        $this->categories->shouldReceive('restore')->once()->with($laptops);

        try {
            $this->service->restore($laptops);
            $this->fail('A child was restored under a trashed parent.');
        } catch (ValidationException) {
        }

        $this->service->restore($laptops);
    }

    public function test_the_full_option_list_is_cached_until_a_category_changes(): void
    {
        $this->categories->shouldReceive('allActive')->twice()->andReturn(
            new Collection([$this->category(1, 'Books')]),
            new Collection([$this->category(1, 'Books'), $this->category(2, 'Toys')]),
        );
        $this->categories->shouldReceive('lockTree');
        $this->categories->shouldReceive('existsBySlug')->andReturnFalse();
        $this->categories->shouldReceive('create')->andReturn($this->category(2, 'Toys'));

        $this->assertSame([1 => 'Books'], $this->service->options());
        $this->assertSame([1 => 'Books'], $this->service->options(), 'served from the cache');

        $this->service->create(['name' => 'Toys', 'slug' => 'toys']);

        $this->assertSame([1 => 'Books', 2 => 'Toys'], $this->service->options());
    }
}
