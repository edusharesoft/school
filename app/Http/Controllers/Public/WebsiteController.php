<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Website\SiteContent;
use Illuminate\View\View;

/**
 * Public institutional website on the central domain. Exposes only public data: branch directory
 * (name, address, phone, e-mail, campus photo, map position), published news and CMS pages.
 */
class WebsiteController extends Controller
{
    public function __construct(protected SiteContent $site) {}

    public function home(): View
    {
        return view('website.home', [
            'institution' => $this->site->institution(),
            'site' => $this->site->settings(),
            'branches' => $this->site->branches(),
            'news' => $this->site->latestNews(limit: 3),
        ]);
    }

    public function branches(): View
    {
        return view('website.branches', [
            'institution' => $this->site->institution(),
            'site' => $this->site->settings(),
            'branches' => $this->site->branches(),
        ]);
    }
}
