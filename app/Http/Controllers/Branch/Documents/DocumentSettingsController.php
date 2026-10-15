<?php

namespace App\Http\Controllers\Branch\Documents;

use App\Http\Controllers\Controller;
use App\Services\Documents\DocumentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Signature, seal, colours and wording of the printed documents (certificates, ID cards, mark sheets). */
class DocumentSettingsController extends Controller
{
    public function __construct(protected DocumentSettings $settings) {}

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('branch.documents.settings', [
            'values' => $this->settings->all(), 'branch' => tenant()->branch(),
            'signatureUrl' => $this->settings->imagePath('signature_path') ? Storage::disk('public')->url($this->settings->get('signature_path')) : null,
            'sealUrl' => $this->settings->imagePath('seal_path') ? Storage::disk('public')->url($this->settings->get('seal_path')) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.update');
        $data = $request->validate([
            'signatory_name' => ['nullable', 'string', 'max:120'],
            'signatory_title' => ['nullable', 'string', 'max:120'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'certificate_style' => ['required', Rule::in(DocumentSettings::CERT_STYLES)],
            'certificate_footer' => ['nullable', 'string', 'max:200'],
            'card_theme' => ['required', Rule::in(DocumentSettings::CARD_THEMES)],
            'card_validity_months' => ['required', 'integer', 'min:1', 'max:120'],
            'card_show_blood_group' => ['nullable', 'boolean'],
            'card_show_guardian_phone' => ['nullable', 'boolean'],
            'card_emergency_phone' => ['nullable', 'string', 'max:40'],
            'card_back_text' => ['nullable', 'string', 'max:400'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'seal' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'remove_signature' => ['nullable', 'boolean'],
            'remove_seal' => ['nullable', 'boolean'],
        ]);

        $this->settings->save($data + ['card_show_blood_group' => $request->boolean('card_show_blood_group'), 'card_show_guardian_phone' => $request->boolean('card_show_guardian_phone')]);

        foreach (['signature' => 'signature_path', 'seal' => 'seal_path'] as $field => $key) {
            $current = (string) $this->settings->get($key);
            if ($request->hasFile($field)) {
                if ($current !== '') {
                    Storage::disk('public')->delete($current);
                }
                $this->settings->setImage($key, $request->file($field)->store('documents/branch-'.tenant()->branchId(), 'public'));
            } elseif ($request->boolean('remove_'.$field) && $current !== '') {
                Storage::disk('public')->delete($current);
                $this->settings->setImage($key, null);
            }
        }

        return back()->with('success', __('Document settings saved.'));
    }
}
