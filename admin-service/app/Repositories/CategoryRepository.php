<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository
{
    /** @return Collection<int, string> id => name */
    public function namesById(): Collection
    {
        return Category::orderBy('name')->pluck('name', 'id');
    }
}
