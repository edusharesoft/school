<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Website\Subscriber;
use App\Services\Website\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Footer newsletter box. Public, rate limited, honeypot protected; an address can always unsubscribe. */
class NewsletterController extends Controller
{
    public function subscribe(Request $request, SiteContent $site): RedirectResponse
    {
        $institution = $site->institution();
        abort_unless($institution, 404);

        // Bots fill the hidden "website" field; humans never see it. Pretend success.
        if ($request->filled('website')) {
            return back()->with('success', __('Thank you! You are subscribed.'));
        }

        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:191']]);
        $email = Str::lower(trim($data['email']));

        $subscriber = Subscriber::query()->where('institution_id', $institution->id)->where('email', $email)->first();
        if ($subscriber) {
            $subscriber->forceFill(['unsubscribed_at' => null, 'locale' => app()->getLocale()])->save();
        } else {
            $subscriber = new Subscriber(['email' => $email, 'locale' => app()->getLocale(), 'ip' => $request->ip()]);
            $subscriber->institution_id = $institution->id;
            $subscriber->unsubscribe_token = Str::random(48);
            $subscriber->save();
        }

        return redirect()->to(route('website.home').'#newsletter')->with('success', __('Thank you! You are subscribed.'));
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = Subscriber::query()->where('unsubscribe_token', $token)->firstOrFail();
        $subscriber->forceFill(['unsubscribed_at' => now()])->save();

        return redirect()->route('website.home')->with('success', __('You have been unsubscribed.'));
    }
}
