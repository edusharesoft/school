<?php

namespace App\Http\Controllers\Branch\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\LibraryCategory;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Book categories (Fiction, Science, Religion …). permission: library.* */
class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('library.view');

        return view('branch.library.categories', ['categories' => LibraryCategory::query()->withCount('books')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('library.create');
        LibraryCategory::query()->create($this->validated($request));

        return back()->with('success', __('Category saved.'));
    }

    public function update(Request $request, LibraryCategory $category): RedirectResponse
    {
        $this->authorize('library.update');
        $category->fill($this->validated($request, $category))->save();

        return back()->with('success', __('Category saved.'));
    }

    public function destroy(LibraryCategory $category): RedirectResponse
    {
        $this->authorize('library.delete');
        if ($category->books()->withTrashed()->exists()) {
            return back()->with('warning', __('Move or delete the books in this category first.'));
        }
        $category->delete();

        return back()->with('success', __('Category deleted.'));
    }

    protected function validated(Request $request, ?LibraryCategory $category = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100', TenantRule::unique('library_categories', 'name', $category?->id)], 'name_bn' => ['nullable', 'string', 'max:100']]);
    }
}
