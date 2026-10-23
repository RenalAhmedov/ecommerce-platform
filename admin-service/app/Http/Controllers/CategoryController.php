<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index(): View
    {
        return view('categories.index', [
            'tree' => $this->categories->tree(),
            'trashed' => $this->categories->trashed(),
        ]);
    }

    public function create(): View
    {
        return view('categories.create', ['parents' => $this->categories->options()]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = $this->categories->create($request->validated());

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" created.");
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', [
            'category' => $category,
            'parents' => $this->categories->options(withoutSubtreeOf: $category->id),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category = $this->categories->update($category, $request->validated());

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" saved.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->categories->delete($category);

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" moved to trash.");
    }

    public function restore(Category $category): RedirectResponse
    {
        $this->categories->restore($category);

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" restored.");
    }
}
