<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sends a signed-in user to the right home for the current host and their portal. */
class HomeRedirectController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return redirect()->to(self::homeFor($user, $tenant));
    }

    public static function homeFor(User $user, TenantContext $tenant): string
    {
        if ($tenant->isCentral()) {
            return route('central.dashboard');
        }

        return match ($user->portal()) {
            'teacher' => route('portal.teacher'),
            'student' => route('portal.student'),
            'parent' => route('portal.parent'),
            default => $user->can('dashboard.branch') ? route('branch.dashboard') : route('branch.switch-branch'),
        };
    }
}
