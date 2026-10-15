<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Models\Assets\Audit;
use App\Models\Assets\AuditItem;
use App\Models\Assets\Category;
use App\Services\Assets\AssetAuditService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Physical verification of the register. assets.update to count, never touches the books. */
class AuditController extends Controller
{
    use AssetLookups;

    public function __construct(protected AssetAuditService $audits) {}

    public function index(): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.audits.index', [
            'audits' => Audit::query()->withCount(['items', 'items as checked_count' => fn ($q) => $q->whereNotNull('result'), 'items as missing_count' => fn ($q) => $q->where('result', 'missing')])->latest('date')->latest('id')->paginate(20),
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'), 'rooms' => $this->rooms(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'date' => ['required', 'date', 'before_or_equal:today'], 'asset_category_id' => ['nullable', TenantRule::exists('asset_categories')],
            'room_id' => ['nullable', TenantRule::exists('rooms', 'id', true)], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $audit = $this->audits->start($data, $request->user());

        return redirect()->route('branch.assets.audits.show', $audit)->with('success', __('Count sheet :no opened.', ['no' => $audit->audit_no]));
    }

    public function show(Audit $audit): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.audits.show', ['audit' => $audit->load(['category', 'room', 'starter']), 'items' => $audit->items()->with('asset:id,code,name,name_bn,serial_no,condition')->orderBy('asset_id')->get()]);
    }

    public function results(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate([
            'results' => ['required', 'array'], 'results.*.result' => ['nullable', Rule::in(AuditItem::RESULTS)],
            'results.*.found_location' => ['nullable', 'string', 'max:150'], 'results.*.note' => ['nullable', 'string', 'max:255'],
        ]);
        $n = $this->audits->saveResults($audit, $data['results']);
        if ($request->boolean('complete')) {
            $this->audits->complete($audit, $request->user());

            return back()->with('success', __('Audit completed — the register is updated.'));
        }

        return back()->with('success', __(':n line(s) saved.', ['n' => $n]));
    }

    public function complete(Request $request, Audit $audit): RedirectResponse
    {
        $this->authorize('assets.update');
        $this->audits->complete($audit, $request->user());

        return back()->with('success', __('Audit completed — the register is updated.'));
    }

    public function cancel(Audit $audit): RedirectResponse
    {
        $this->authorize('assets.update');
        $this->audits->cancel($audit);

        return redirect()->route('branch.assets.audits.index')->with('success', __('Audit cancelled.'));
    }
}
