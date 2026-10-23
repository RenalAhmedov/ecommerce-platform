<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(private readonly CategoryRepository $categories) {}

    /**
     * Active categories in tree order (each parent followed by its children,
     * siblings by name). A category whose parent is trashed is shown as a root.
     * $withoutSubtreeOf leaves out that category and everything below it, which
     * are exactly the parents it may not be moved under.
     *
     * @return list<array{category: Category, depth: int}>
     */
    public function tree(?int $withoutSubtreeOf = null): array
    {
        $categories = $this->categories->allActive();
        $activeIds = $categories->modelKeys();
        $byParent = $categories->groupBy(fn (Category $category) => in_array($category->parent_id, $activeIds, true) ? $category->parent_id : 0);

        $rows = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $byParent, $withoutSubtreeOf): void {
            foreach ($byParent->get($parentId, []) as $category) {
                if ($category->id === $withoutSubtreeOf) {
                    continue;
                }
                $rows[] = ['category' => $category, 'depth' => $depth];
                $walk($category->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $rows;
    }

    /** @return array<int, string> id => indented name, for select inputs */
    public function options(?int $withoutSubtreeOf = null): array
    {
        return collect($this->tree($withoutSubtreeOf))
            ->mapWithKeys(fn (array $row) => [$row['category']->id => str_repeat('— ', $row['depth']).$row['category']->name])
            ->all();
    }

    /** @return Collection<int, Category> */
    public function trashed(): Collection
    {
        return $this->categories->allTrashed();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $this->categories->lockTree();
            $this->ensureSlugAvailable($data['slug']);

            return $this->categories->create($data);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $this->categories->lockTree();
            $category->fill($data);

            if ($category->isDirty('slug')) {
                $this->ensureSlugAvailable($category->slug, $category->id);
            }
            if ($category->isDirty('parent_id') && $category->parent_id !== null) {
                $this->ensureValidParent($category, $category->parent_id);
            }

            $this->categories->save($category);

            return $category;
        });
    }

    /**
     * Products keep their category_id, so restoring the category brings them
     * back into it; until then they show as uncategorised.
     */
    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $this->categories->lockTree();

            if ($this->categories->hasActiveChildren($category)) {
                throw ValidationException::withMessages([
                    'category' => "\"{$category->name}\" still has subcategories. Move or delete them first.",
                ]);
            }

            $this->categories->delete($category);
        });
    }

    public function restore(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $this->categories->lockTree();

            if ($category->parent_id !== null && ! $this->categories->isActive($category->parent_id)) {
                throw ValidationException::withMessages([
                    'category' => "Restore the parent of \"{$category->name}\" first.",
                ]);
            }

            $this->categories->restore($category);
        });
    }

    /** @throws ValidationException */
    private function ensureSlugAvailable(string $slug, ?int $ignoreId = null): void
    {
        if ($this->categories->existsBySlug($slug, $ignoreId)) {
            throw ValidationException::withMessages(['slug' => 'A category with this slug already exists.']);
        }
    }

    /** @throws ValidationException */
    private function ensureValidParent(Category $category, int $parentId): void
    {
        if ($this->categories->isSelfOrDescendant($parentId, $category->id)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be moved under itself or one of its subcategories.',
            ]);
        }
    }
}
