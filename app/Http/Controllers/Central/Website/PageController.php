<?php

namespace App\Http\Controllers\Central\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\PageRequest;
use App\Models\Website\Page;
use App\Services\Website\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Layout and route-name prefix of the screens; the branch-site controller overrides them. */
    protected string $layout = 'layouts.central';

    protected string $routePrefix = 'central.website.';

    public function __construct(protected SiteContent $site) {}

    /** null = the institution-wide site, otherwise the branch whose site is being edited. */
    protected function siteBranchId(): ?int
    {
        return null;
    }

    protected function own(Page $page): Page
    {
        abort_unless($page->branch_id === $this->siteBranchId(), 404);

        return $page;
    }

    protected function ui(array $data = []): array
    {
        return $data + ['layout' => $this->layout, 'routePrefix' => $this->routePrefix];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Page::class);
        $institution = $this->site->forAdmin($request->user());

        $pages = Page::query()->where('institution_id', $institution?->id)->ofSite($this->siteBranchId())
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where(fn ($q) => $q->where('title', 'like', "%{$t}%")->orWhere('slug', 'like', "%{$t}%")))
            ->orderByRaw("CASE placement WHEN 'menu' THEN 0 WHEN 'footer_quick' THEN 1 WHEN 'footer_campus' THEN 2 ELSE 3 END")
            ->orderBy('sort_order')->orderBy('title')->paginate(30)->withQueryString();

        return view('central.website.pages.index', $this->ui(compact('pages')));
    }

    public function create(): View
    {
        $this->authorize('create', Page::class);

        return view('central.website.pages.form', $this->ui(['page' => new Page(['placement' => 'hidden', 'status' => 'published', 'sort_order' => 0])]));
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $institution = $this->site->forAdmin($request->user());
        abort_unless($institution, 404);

        $page = new Page($request->safe()->except([]));
        $page->institution_id = $institution->id;
        $page->branch_id = $this->siteBranchId();
        $page->created_by = $request->user()->id;
        $page->sort_order ??= 0;
        $page->save();

        return redirect()->route($this->routePrefix.'pages.index')->with('success', __('Page saved.'));
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $this->own($page));

        return view('central.website.pages.form', $this->ui(compact('page')));
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $this->own($page);
        $page->update($request->safe()->except([]) + ['sort_order' => $request->integer('sort_order')]);

        return redirect()->route($this->routePrefix.'pages.index')->with('success', __('Page saved.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('delete', $this->own($page));

        // Free the slug (the unique index also covers soft-deleted rows).
        $page->forceFill(['slug' => $page->slug.'-del-'.$page->id])->save();
        $page->delete();

        return redirect()->route($this->routePrefix.'pages.index')->with('success', __('Page deleted.'));
    }
}
