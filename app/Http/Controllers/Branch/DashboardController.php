<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->dashboard->range($this->filters($request));

        return view('branch.dashboard', ['filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->branch($this->filters($request)));
    }

    protected function filters(Request $request): array
    {
        return $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
    }
}
