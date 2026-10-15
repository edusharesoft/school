<?php

namespace App\Http\Controllers\Central\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\Subscriber;
use App\Services\Website\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Newsletter sign-ups collected by the footer box (read-only list + CSV export). */
class SubscriberController extends Controller
{
    public function __construct(protected SiteContent $site) {}

    public function index(Request $request): View
    {
        $this->authorize('website.view');
        $institution = $this->site->forAdmin($request->user());

        $query = Subscriber::query()->where('institution_id', $institution?->id)
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where('email', 'like', "%{$t}%"));

        return view('central.website.subscribers', [
            'subscribers' => (clone $query)->latest('id')->paginate(30)->withQueryString(),
            'active' => (clone $query)->whereNull('unsubscribed_at')->count(),
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('website.view');
        $institution = $this->site->forAdmin($request->user());

        $rows = Subscriber::query()->where('institution_id', $institution?->id)->whereNull('unsubscribed_at')->orderBy('id')->get(['email', 'locale', 'created_at']);
        $csv = "email,language,subscribed_at\n".$rows->map(fn ($r) => implode(',', [
            '"'.str_replace('"', '""', $this->safeCell($r->email)).'"', $r->locale, $r->created_at->toDateTimeString(),
        ]))->implode("\n");

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="newsletter-subscribers.csv"']);
    }

    /** Neutralise spreadsheet formulas in exported cells. */
    protected function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
