<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The category tree. Only this service writes categories (the Nest API only
 * reads them), so the dropdown options are cached in Redis and cleared by
 * every write here, after its transaction has committed.
 */
class CategoryService
{
    private const OPTIONS_CACHE_KEY = 'categories:options';

    /** Safety net in case a read races a write; normally the entry is cleared by the write. */
    private const OPTIONS_CACHE_SECONDS = 3600;

    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly Cache $cache,
    ) {}

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

    /**
     * id => indented name, for select inputs. The full list (used on every
     * product page) is cached; the list without a subtree (only on the
     * category edit page) is built fresh.
     *
     * @return array<int, string>
     */
    public function options(?int $withoutSubtreeOf = null): array
    {
        if ($withoutSubtreeOf !== null) {
            return $this->buildOptions($withoutSubtreeOf);
        }

        return $this->cache->remember(self::OPTIONS_CACHE_KEY, self::OPTIONS_CACHE_SECONDS, fn () => $this->buildOptions(null));
    }

    /** @return array<string, int> slug => id of active categories, for imports */
    public function idsBySlug(): array
    {
        return $this->categories->idsBySlug();
    }

    /** @return Collection<int, Category> */
    public function trashed(): Collection
    {
        return $this->categories->allTrashed();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Category
    {
        $category = DB::transaction(function () use ($data) {
            $this->categories->lockTree();
            $this->ensureSlugAvailable($data['slug']);

            return $this->categories->create($data);
        });
        $this->forgetOptions();

        Log::info('Category created', ['category_id' => $category->id, 'slug' => $category->slug, 'parent_id' => $category->parent_id]);

        return $category;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Category $category, array $data): Category
    {
        $changed = DB::transaction(function () use ($category, $data) {
            $this->categories->lockTree();
            $category->fill($data);

            if ($category->isDirty('slug')) {
                $this->ensureSlugAvailable($category->slug, $category->id);
            }
            if ($category->isDirty('parent_id') && $category->parent_id !== null) {
                $this->ensureValidParent($category, $category->parent_id);
            }

            $changed = array_keys($category->getDirty());
            $this->categories->save($category);

            return $changed;
        });
        $this->forgetOptions();

        if ($changed !== []) {
            Log::info('Category updated', ['category_id' => $category->id, 'changed' => $changed]);
        }

        return $category;
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
        $this->forgetOptions();

        Log::info('Category moved to trash', ['category_id' => $category->id, 'slug' => $category->slug]);
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
        $this->forgetOptions();

        Log::info('Category restored', ['category_id' => $category->id, 'slug' => $category->slug]);
    }

    /** @return array<int, string> */
    private function buildOptions(?int $withoutSubtreeOf): array
    {
        return collect($this->tree($withoutSubtreeOf))
            ->mapWithKeys(fn (array $row) => [$row['category']->id => str_repeat('— ', $row['depth']).$row['category']->name])
            ->all();
    }

    private function forgetOptions(): void
    {
        $this->cache->forget(self::OPTIONS_CACHE_KEY);
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
