<?php

namespace App\Http\Controllers\Branch\Website;

use App\Http\Controllers\Central\Website\PageController as CentralPageController;
use App\Services\Website\BranchSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pages (About Us, Academic, Regulations, Contact …) of this branch's own public website. */
class PageController extends CentralPageController
{
    protected string $layout = 'layouts.branch';

    protected string $routePrefix = 'branch.website.';

    protected function siteBranchId(): ?int
    {
        return tenant()->branchId();
    }

    /** One click: create the usual pages (About Us, Academic, Regulations, Contact, footer links). Existing pages are never touched. */
    public function defaults(Request $request, BranchSite $site): RedirectResponse
    {
        $this->authorize('create', \App\Models\Website\Page::class);
        $made = $site->createDefaultPages(tenant()->branch(), $request->user()->id);

        return redirect()->route('branch.website.pages.index')->with('success', $made
            ? __(':count pages were created. Open each one to write its content.', ['count' => bn_digits($made)])
            : __('The standard pages already exist.'));
    }
}
