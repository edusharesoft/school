<?php

namespace App\Http\Controllers\Branch\Communication;

use App\Http\Controllers\Controller;
use App\Models\Communication\NotificationOptOut;
use App\Services\Notifications\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Numbers and addresses that must not receive SMS / WhatsApp / e-mail (emergency notices excepted). */
class OptOutController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('notifications.view');

        return view('branch.communication.opt-outs', [
            'optOuts' => NotificationOptOut::query()->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where('address', 'like', '%'.preg_replace('/\s+/', '', $t).'%'))
                ->latest('id')->paginate(40)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('notifications.configure');
        $data = $request->validate([
            'channel' => ['required', Rule::in(['sms', 'email', 'whatsapp'])],
            'address' => ['required', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);
        $address = $data['channel'] === 'email'
            ? (filter_var(trim($data['address']), FILTER_VALIDATE_EMAIL) ? mb_strtolower(trim($data['address'])) : null)
            : PhoneNumber::normalize($data['address']);
        if (! $address) {
            return back()->withErrors(['address' => __('Enter a valid Bangladeshi mobile number or e-mail address.')])->withInput();
        }
        $optOut = NotificationOptOut::query()->firstOrNew(['channel' => $data['channel'], 'address' => $address]);
        $optOut->fill(['reason' => $data['reason'] ?? null])->forceFill(['created_by' => $request->user()->id])->save();

        return back()->with('success', __(':address will not receive :channel messages.', ['address' => $address, 'channel' => __(strtoupper($data['channel']) === 'EMAIL' ? 'E-mail' : strtoupper($data['channel']))]));
    }

    public function destroy(NotificationOptOut $optOut): RedirectResponse
    {
        $this->authorize('notifications.configure');
        $optOut->delete();

        return back()->with('success', __('Opt-out removed.'));
    }
}
