<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\DomainRequest;
use App\Models\Branch;
use App\Models\Domain;
use App\Services\Tenancy\BranchService;
use Illuminate\Http\RedirectResponse;

class BranchDomainController extends Controller
{
    public function __construct(protected BranchService $branches) {}

    public function store(DomainRequest $request, Branch $branch): RedirectResponse
    {
        $this->branches->attachDomain($branch, $request->validated('domain'), $request->boolean('is_primary'));

        return back()->with('success', __('Domain added. Point its DNS A/CNAME record to this server.'));
    }

    public function destroy(Branch $branch, Domain $domain): RedirectResponse
    {
        $this->authorize('manageDomains', $branch);

        $this->branches->detachDomain($domain);

        return back()->with('success', __('Domain removed.'));
    }
}
