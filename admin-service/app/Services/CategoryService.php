<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use Illuminate\Support\Collection;

class CategoryService
{
    public function __construct(private readonly CategoryRepository $categories) {}

    /** @return Collection<int, string> id => name, for select inputs */
    public function options(): Collection
    {
        return $this->categories->namesById();
    }
}
