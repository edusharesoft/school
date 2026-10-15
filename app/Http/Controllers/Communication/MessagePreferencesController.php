<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Communication\NotificationOptOut;
use App\Services\Notifications\PhoneNumber;
use App\Services\Portal\PortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Guardians and students choose which channels the school may use for routine messages.
 * Stored as opt-outs of their own number / e-mail; emergency notices are still delivered.
 */
class MessagePreferencesController extends Controller
{
    public function edit(Request $request, PortalService $portal): View
    {
        [$phone, $email] = $this->contacts($request, $portal);

        return view('communication.preferences', [
            'phone' => $phone, 'email' => $email,
            'optedOut' => NotificationOptOut::query()->where(fn ($q) => $q->where('address', $phone ?? '-')->orWhere('address', $email ?? '-'))->pluck('channel')->flip(),
        ]);
    }

    public function update(Request $request, PortalService $portal): RedirectResponse
    {
        [$phone, $email] = $this->contacts($request, $portal);
        foreach (['sms' => $phone, 'whatsapp' => $phone, 'email' => $email] as $channel => $address) {
            if (! $address) {
                continue;
            }
            if ($request->boolean($channel)) {
                NotificationOptOut::query()->where(['channel' => $channel, 'address' => $address])->delete();
            } else {
                $optOut = NotificationOptOut::query()->firstOrNew(['channel' => $channel, 'address' => $address]);
                $optOut->fill(['reason' => __('Chosen in the portal')])->forceFill(['created_by' => $request->user()->id])->save();
            }
        }

        return back()->with('success', __('Your message preferences are saved.'));
    }

    /** @return array{0:?string,1:?string} the user's own number and e-mail as the school has them */
    protected function contacts(Request $request, PortalService $portal): array
    {
        $user = $request->user();
        $person = match ($user->portal()) {
            'parent' => $portal->guardianFor($user),
            'student' => $portal->studentFor($user),
            default => null,
        };
        abort_unless($person, 404);
        $email = $person->email ?: $user->email;

        return [PhoneNumber::normalize($person->phone), filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : null];
    }
}
