<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\BrandingSettings;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Central "Settings": logo, footer, login / registration page look, registration switch.
 * Super Admin edits the platform level (main domain + defaults for everyone);
 * an Institution Administrator edits the institution level (all of that institution's branch login pages).
 */
class SettingsController extends Controller
{
    public function __construct(protected BrandingSettings $branding) {}

    public function edit(Request $request, TenantContext $tenant): View
    {
        abort_unless($request->user()->can('settings.view'), 403);
        [$level, $platform] = $this->level($request, $tenant);

        return view('central.settings', [
            'values' => $this->branding->values($level),
            'platform' => $platform,
            'institution' => $tenant->institution(),
        ]);
    }

    public function update(Request $request, TenantContext $tenant, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);
        [$level, $platform] = $this->level($request, $tenant);

        $request->validate(BrandingSettings::rules($platform));
        $this->branding->save($request, $level, $platform ? 'platform' : 'institution-'.$tenant->institutionId(), $platform);

        $audit->log('settings.branding-updated', $tenant->institution(), [], ['level' => $level]);

        return back()->with('success', __('Settings saved.'));
    }

    /** @return array{0:string,1:bool} */
    protected function level(Request $request, TenantContext $tenant): array
    {
        return $request->user()->isPlatformOwner() || ! $tenant->institutionId() ? ['platform', true] : ['institution', false];
    }
}
