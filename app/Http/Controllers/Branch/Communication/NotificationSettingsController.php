<?php

namespace App\Http\Controllers\Branch\Communication;

use App\Http\Controllers\Controller;
use App\Models\Communication\NotificationMessage;
use App\Services\Notifications\EventCatalog;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\NotificationSettings;
use App\Services\Notifications\PhoneNumber;
use App\Services\Notifications\SmsCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Branch notification providers (SMS gateway, SMTP, WhatsApp Cloud API), channels per event, reminders, test message. */
class NotificationSettingsController extends Controller
{
    public function __construct(protected NotificationSettings $settings) {}

    public function edit(): View
    {
        $this->authorize('notifications.configure');
        $events = config('notifications.events');

        return view('branch.communication.settings', [
            'values' => $this->settings->formValues(),
            'events' => $events,
            'eventChannels' => collect($events)->mapWithKeys(fn ($e, $key) => [$key => $this->settings->eventChannels($key)]),
            'drivers' => config('notifications.sms_drivers'),
            'webhookUrl' => tenant()->branch()->url('/webhooks/whatsapp'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('notifications.configure');
        $channels = config('notifications.channels');
        $data = $request->validate([
            'sms_enabled' => ['nullable', 'boolean'],
            'email_enabled' => ['nullable', 'boolean'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'default_locale' => ['required', Rule::in(['bn', 'en'])],
            'sms_driver' => ['required', Rule::in(array_keys(config('notifications.sms_drivers')))],
            'sms.sid' => ['nullable', 'string', 'max:60'],
            'sms.sender_id' => ['nullable', 'string', 'max:60'],
            'sms.server' => ['nullable', 'url:https,http', 'max:191'],
            'sms.device' => ['nullable', 'string', 'max:20', 'regex:/^\d+(\|\d+)?$/'],
            'sms.api_token' => ['nullable', 'string', 'max:255'],
            'sms.api_key' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:191'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'smtp.host' => ['nullable', 'string', 'max:191'],
            'smtp.port' => ['nullable', 'integer', 'between:1,65535'],
            'smtp.encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'smtp.username' => ['nullable', 'string', 'max:191'],
            'smtp.password' => ['nullable', 'string', 'max:255'],
            'whatsapp.phone_number_id' => ['nullable', 'string', 'max:40', 'regex:/^\d+$/'],
            'whatsapp.api_version' => ['nullable', 'string', 'max:10', 'regex:/^v\d+\.\d+$/'],
            'whatsapp.verify_token' => ['nullable', 'string', 'max:120'],
            'whatsapp.access_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp.app_secret' => ['nullable', 'string', 'max:255'],
            'event_channels' => ['nullable', 'array'],   // keys are event names with dots — read raw below, filtered to known values
            'fee_reminder_enabled' => ['nullable', 'boolean'],
            'fee_reminder_days' => ['required', 'integer', 'between:0,60'],
        ]);

        foreach (['sms_enabled', 'email_enabled', 'whatsapp_enabled', 'fee_reminder_enabled'] as $flag) {
            $this->settings->set($flag, $request->boolean($flag));
        }
        $this->settings->set('default_locale', $data['default_locale']);
        $this->settings->set('fee_reminder_days', (int) $data['fee_reminder_days']);
        $this->settings->set('sms_driver', $data['sms_driver']);
        $this->settings->set('sms_config', array_filter(['sid' => $data['sms']['sid'] ?? null, 'sender_id' => $data['sms']['sender_id'] ?? null,
            'server' => $data['sms']['server'] ?? null, 'device' => $data['sms']['device'] ?? null]));
        $this->settings->saveSecrets('sms_secrets', $data['sms'] ?? [], ['api_token', 'api_key']);
        $this->settings->set('mail_from_address', $data['mail_from_address'] ?? null);
        $this->settings->set('mail_from_name', $data['mail_from_name'] ?? null);
        $this->settings->set('smtp_config', array_filter(['host' => $data['smtp']['host'] ?? null, 'port' => $data['smtp']['port'] ?? null,
            'encryption' => $data['smtp']['encryption'] ?? null, 'username' => $data['smtp']['username'] ?? null]));
        $this->settings->saveSecrets('smtp_secrets', $data['smtp'] ?? [], ['password']);
        $this->settings->set('whatsapp_config', array_filter(['phone_number_id' => $data['whatsapp']['phone_number_id'] ?? null,
            'api_version' => $data['whatsapp']['api_version'] ?? null]));
        $this->settings->saveSecrets('whatsapp_secrets', $data['whatsapp'] ?? [], ['access_token', 'app_secret', 'verify_token']);

        $matrix = [];
        $posted = (array) $request->input('event_channels', []);
        foreach (array_keys(EventCatalog::all()) as $event) {
            $matrix[$event] = array_values(array_intersect($channels, (array) ($posted[$event] ?? [])));
        }
        $this->settings->set('event_channels', $matrix);

        return back()->with('success', __('Notification settings saved.'));
    }

    /** Send one test message through the configured provider and show the provider's answer. */
    public function test(Request $request, NotificationHub $hub): RedirectResponse
    {
        $this->authorize('notifications.configure');
        $data = $request->validate([
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'to' => ['required', 'string', 'max:191'],
        ]);
        $to = $data['channel'] === 'sms' ? PhoneNumber::normalize($data['to']) : (filter_var($data['to'], FILTER_VALIDATE_EMAIL) ?: null);
        if (! $to) {
            return back()->withErrors(['to' => __('Enter a valid Bangladeshi mobile number or e-mail address.')])->withInput();
        }
        $text = __('Test message from :school. Your notification settings work.', ['school' => tenant()->branch()->displayName()]);
        $message = new NotificationMessage([
            'event' => 'message.general', 'channel' => $data['channel'], 'recipient_type' => 'user', 'recipient_id' => $request->user()->id,
            'recipient_name' => $request->user()->name, 'to' => $to, 'locale' => app()->getLocale() === 'bn' ? 'bn' : 'en',
            'subject' => __('Test message'), 'body' => $text, 'segments' => $data['channel'] === 'sms' ? SmsCounter::segments($text) : 0,
            'created_by' => $request->user()->id,
        ]);
        $message->forceFill(['status' => 'queued'])->save();
        $hub->process($message->id);
        $message->refresh();

        return $message->status === 'failed' || $message->status === 'queued'
            ? back()->withErrors(['to' => __('The provider refused the message: :error', ['error' => $message->error])])->withInput()
            : back()->with('success', __('Test message accepted by :provider (:status).', ['provider' => $message->provider, 'status' => __($message->status)]));
    }
}
