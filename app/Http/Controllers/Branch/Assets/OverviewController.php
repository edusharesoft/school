<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Services\Assets\AssetReportService;
use Illuminate\View\View;

/** Asset home: value, maintenance due, warranty, books check. permission: assets.view */
class OverviewController extends Controller
{
    public function index(AssetReportService $reports): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.index', $reports->overview());
    }
}
