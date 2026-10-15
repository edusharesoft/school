<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Website\Page;
use App\Services\Website\SiteContent;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(SiteContent $site, string $slug): View
    {
        $institution = $site->institution();
        abort_unless($institution, 404);

        $page = Page::query()->where('institution_id', $institution->id)->whereNull('branch_id')->published()->where('slug', $slug)->firstOrFail();

        return view('website.page', ['page' => $page, 'institution' => $institution, 'site' => $site->settings()]);
    }
}
