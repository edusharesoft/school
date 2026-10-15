<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Services\Settings\BrandingSettings;
use App\Services\Tenancy\BranchService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Branch "Settings" for the Branch Administrator: branch profile, logo & colour, and the look of this
 * branch's login page (overrides the institution / platform defaults). Subdomain, code and status stay
 * with the central administration.
 */
class SettingsController extends Controller
{
    public function __construct(protected BrandingSettings $branding) {}

    public function edit(Request $request, TenantContext $tenant): View
    {
        abort_unless($request->user()->can('settings.view'), 403);

        return view('branch.settings', ['branch' => $tenant->branch(), 'values' => $this->branding->values('branch')]);
    }

    public function update(Request $request, TenantContext $tenant, BranchService $branches): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);
        $branch = $tenant->branch();

        $data = $request->validate([
            'name_bn' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'principal_name' => ['nullable', 'string', 'max:191'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ] + collect(BrandingSettings::rules(false))->only(['auth_login_video', 'auth_show_video', 'auth_dim', 'auth_login_bg'])->all());

        $profile = collect($data)->only(['name_bn', 'email', 'phone', 'address', 'principal_name', 'primary_color', 'locale'])->all();

        if ($request->boolean('remove_logo') && $branch->logo_path) {
            Storage::disk('public')->delete($branch->logo_path);
            $profile['logo_path'] = null;
        }
        if ($request->hasFile('logo')) {
            $profile['logo_path'] = $request->file('logo')->store('branches/'.$branch->id, 'public');
        }
        $branches->update($branch, $profile);

        // Branch login page look (only the keys a branch may override).
        $request->merge(['site_name' => null, 'footer_text' => null, 'auth_register_video' => null]);
        $this->branding->save($request, 'branch', 'branch-'.$branch->id, false);

        return back()->with('success', __('Settings saved.'));
    }
}
