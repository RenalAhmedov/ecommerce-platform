<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** @var array<string, array{string, ?string}> slug => [name, parent slug] */
    private const CATEGORIES = [
        'electronics' => ['Electronics', null],
        'audio' => ['Audio', 'electronics'],
        'computers' => ['Computers', 'electronics'],
        'laptops' => ['Laptops', 'computers'],
        'computer-accessories' => ['Accessories', 'computers'],
        'home-kitchen' => ['Home & Kitchen', null],
        'kitchen' => ['Kitchen', 'home-kitchen'],
        'decor' => ['Decor', 'home-kitchen'],
        'clothing' => ['Clothing', null],
        'books' => ['Books', null],
    ];

    public function run(CategoryService $categories): void
    {
        foreach (self::CATEGORIES as $slug => [$name, $parentSlug]) {
            if (Category::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $categories->create([
                'name' => $name,
                'slug' => $slug,
                'parent_id' => $parentSlug === null ? null : Category::where('slug', $parentSlug)->value('id'),
            ]);
        }
    }
}
