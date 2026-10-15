<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Models\Assets\Category;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Asset categories with the depreciation defaults offered when an asset is registered. */
class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.categories', ['categories' => Category::query()->withCount('assets')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('assets.create');
        Category::query()->create($this->validated($request));

        return back()->with('success', __('Category added.'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('assets.update');
        $category->update($this->validated($request, $category));

        return back()->with('success', __('Category saved.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('assets.delete');
        if ($category->assets()->exists()) {
            return back()->with('warning', __('This category has assets — mark it inactive instead.'));
        }
        $category->delete();

        return back()->with('success', __('Category deleted.'));
    }

    protected function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', TenantRule::unique('asset_categories', 'name', $category?->id)],
            'kind' => ['required', Rule::in(Category::KINDS)],
            'depreciation_method' => ['required', Rule::in(Category::METHODS)],
            'useful_life_months' => ['nullable', 'integer', 'min:0', 'max:1200', 'required_if:depreciation_method,straight_line'],
            'salvage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'declining_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:depreciation_method,declining_balance'],
        ]);
        $data['useful_life_months'] = (int) ($data['useful_life_months'] ?? 0);
        $data['salvage_percent'] = (float) ($data['salvage_percent'] ?? 0);
        $data['declining_rate'] = (float) ($data['declining_rate'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
