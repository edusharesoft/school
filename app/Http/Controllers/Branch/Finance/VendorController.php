<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Suppliers / payees used on expense vouchers. */
class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('expenses.view');

        return view('branch.finance.vendors.index', [
            'vendors' => Vendor::query()->withCount('expenses')->withSum(['expenses as spent' => fn ($q) => $q->whereIn('status', ['approved', 'paid'])], 'amount')
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where('name', 'like', "%$t%")->orWhere('phone', 'like', "%$t%"))
                ->orderBy('name')->paginate(30)->withQueryString(),
            'editing' => $request->integer('edit') ? Vendor::query()->find($request->integer('edit')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('expenses.create');
        Vendor::query()->create($this->validated($request));

        return redirect()->route('branch.finance.vendors.index')->with('success', __('Supplier saved.'));
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('expenses.update');
        $vendor->update($this->validated($request));

        return redirect()->route('branch.finance.vendors.index')->with('success', __('Supplier saved.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'bin' => ['nullable', 'string', 'max:30'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
