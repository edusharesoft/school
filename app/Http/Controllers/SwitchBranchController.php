<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\Tenancy\HandoffService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Safe branch switching for users with access to several branches.
 * Uses single-use handoff tokens because sessions are host-only.
 */
class SwitchBranchController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        /** @var User $user */
        $user = $request->user();

        $branches = Branch::query()->visibleTo($user)->active()->with('institution')->ordered()->get();

        return view('shared.switch-branch', [
            'branches' => $branches,
            'currentBranchId' => $tenant->branchId(),
            'canUseCentral' => $user->canUseCentral(),
            'layout' => $tenant->isCentral() ? 'layouts.central' : 'layouts.branch',
        ]);
    }

    public function switch(Request $request, Branch $branch, HandoffService $handoff): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($branch->isActive() && $user->canAccessBranch($branch), 403);

        return redirect()->away($handoff->issue($user, $branch));
    }

    public function central(Request $request, HandoffService $handoff): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canUseCentral(), 403);

        return redirect()->away($handoff->issue($user, null));
    }
}
