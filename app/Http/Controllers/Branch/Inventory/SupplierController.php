<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\Vendor;
use App\Services\Inventory\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Suppliers (the same records as Accounts → Suppliers) with what the branch owes each of them. */
class SupplierController extends Controller
{
    public function index(Request $request, ProcurementService $procurement): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.suppliers', [
            'vendors' => Vendor::query()->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
                ->orderBy('name')->paginate(30)->withQueryString(),
            'payables' => $procurement->payables(),
            'editing' => $request->integer('edit') ? Vendor::query()->find($request->integer('edit')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        Vendor::query()->create($this->validated($request));

        return redirect()->route('branch.inventory.suppliers.index')->with('success', __('Supplier saved.'));
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('inventory.update');
        $vendor->update($this->validated($request));

        return redirect()->route('branch.inventory.suppliers.index')->with('success', __('Supplier saved.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:20'], 'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'], 'bin' => ['nullable', 'string', 'max:30'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
