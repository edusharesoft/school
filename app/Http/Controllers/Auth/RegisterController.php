<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Branch;
use App\Services\Registration\InstitutionRegistrationService;
use App\Support\AuthTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Central-domain self registration of a new institution (Settings → "Allow new institution registration"). */
class RegisterController extends Controller
{
    public function create(): View
    {
        abort_unless(AuthTheme::registrationOpen(), 404);

        return view('auth.register', ['baseDomain' => config('tenancy.base_domain')]);
    }

    public function store(RegisterRequest $request, InstitutionRegistrationService $service): RedirectResponse
    {
        abort_unless(AuthTheme::registrationOpen(), 404);

        [, $user] = $service->register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('tenant_key', 'central');

        return redirect()->route('central.dashboard')
            ->with('success', __('Welcome! Your institution is ready. Next: create the academic year and open your first session.'));
    }

    /** GET /register/check-subdomain?subdomain=… → {valid, available, subdomain, host} */
    public function checkSubdomain(Request $request): JsonResponse
    {
        abort_unless(AuthTheme::registrationOpen(), 404);

        $sub = trim(strtolower((string) $request->query('subdomain')), '-');
        $valid = (bool) preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $sub) && strlen($sub) >= 3 && strlen($sub) <= 63
            && ! in_array($sub, config('tenancy.reserved_subdomains'), true);
        $available = $valid && ! Branch::withTrashed()->where('subdomain', $sub)->exists();

        return response()->json([
            'valid' => $valid,
            'available' => $available,
            'subdomain' => $sub,
            'host' => $sub.'.'.config('tenancy.base_domain'),
        ]);
    }
}
