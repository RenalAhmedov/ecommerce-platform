<?php

namespace App\Repositories;

use App\Models\Category;
use App\Support\AdvisoryLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryRepository
{
    /** @return Collection<int, Category> with products_count and children_count */
    public function allActive(): Collection
    {
        return Category::withCount(['products', 'children'])->orderBy('name')->get();
    }

    /** @return Collection<int, Category> */
    public function allTrashed(): Collection
    {
        return Category::onlyTrashed()
            ->with(['parent' => fn ($query) => $query->withTrashed()])
            ->latest('deleted_at')
            ->get();
    }

    /** @return array<string, int> slug => id of active categories */
    public function idsBySlug(): array
    {
        return Category::pluck('id', 'slug')->all();
    }

    public function lockTree(): void
    {
        AdvisoryLock::acquire([AdvisoryLock::categoryTree()]);
    }

    // Deleted categories keep their slug reserved, so a restore can't create a duplicate.
    public function existsBySlug(string $slug, ?int $ignoreId = null): bool
    {
        return Category::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function isActive(int $id): bool
    {
        return Category::whereKey($id)->exists();
    }

    public function hasActiveChildren(Category $category): bool
    {
        return $category->children()->exists();
    }

    public function isSelfOrDescendant(int $candidateId, int $categoryId): bool
    {
        $result = DB::selectOne(
            'WITH RECURSIVE chain AS (
                SELECT id, parent_id FROM categories WHERE id = ?
                UNION
                SELECT c.id, c.parent_id FROM categories c JOIN chain ON c.id = chain.parent_id
            )
            SELECT EXISTS (SELECT 1 FROM chain WHERE id = ?) AS found',
            [$candidateId, $categoryId],
        );

        return (bool) $result->found;
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Category
    {
        return Category::create($attributes);
    }

    public function save(Category $category): void
    {
        $category->save();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function restore(Category $category): void
    {
        $category->restore();
    }
}
