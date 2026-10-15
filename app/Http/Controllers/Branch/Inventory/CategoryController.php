<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Inventory\Category;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Item categories (general / laboratory) and the expense account charged when their items are issued. */
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.categories', [
            'categories' => Category::query()->with('expenseAccount:id,code,name')->withCount('items')->orderBy('name')->get(),
            'accounts' => Account::query()->postable()->where('type', 'expense')->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        Category::query()->create($this->validated($request));

        return back()->with('success', __('Category saved.'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('inventory.update');
        $category->update($this->validated($request, $category));

        return back()->with('success', __('Category saved.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('inventory.delete');
        if ($category->items()->withTrashed()->exists()) {
            return back()->with('warning', __('Items use this category — mark it inactive instead.'));
        }
        $category->delete();

        return back()->with('success', __('Category deleted.'));
    }

    protected function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', TenantRule::unique('inventory_categories', 'name', $category?->id)],
            'kind' => ['required', Rule::in(Category::KINDS)],
            'expense_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('branch_id', tenant()->branchId())->where('type', 'expense')->where('is_group', false)],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
