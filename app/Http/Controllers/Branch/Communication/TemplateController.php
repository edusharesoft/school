<?php

namespace App\Http\Controllers\Branch\Communication;

use App\Http\Controllers\Controller;
use App\Models\Communication\MessageTemplate;
use App\Services\Notifications\EventCatalog;
use App\Services\Notifications\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Branch wording of every notification, in Bangla and English: the SMS text, the e-mail / in-app subject and body,
 * and the name of the approved WhatsApp template. Blank fields fall back to the defaults.
 */
class TemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('notifications.configure');

        return view('branch.communication.templates.index', [
            'events' => config('notifications.events'),
            'customised' => MessageTemplate::query()->distinct()->pluck('event')->flip(),
        ]);
    }

    public function edit(string $event, TemplateRenderer $renderer): View
    {
        $this->authorize('notifications.configure');
        $config = EventCatalog::get($event) ?? abort(404);
        $values = [];
        foreach (['bn', 'en'] as $locale) {
            $message = $renderer->template($event, 'email', $locale);
            $values[$locale] = [
                'sms' => $renderer->template($event, 'sms', $locale)['body'],
                'subject' => $message['subject'],
                'body' => $message['body'],
                'whatsapp_template' => $renderer->template($event, 'whatsapp', $locale)['whatsapp_template'],
                'whatsapp_language' => $renderer->template($event, 'whatsapp', $locale)['whatsapp_language'],
            ];
        }

        return view('branch.communication.templates.edit', ['event' => $event, 'config' => $config, 'values' => $values]);
    }

    public function update(Request $request, string $event, TemplateRenderer $renderer): RedirectResponse
    {
        $this->authorize('notifications.configure');
        $config = EventCatalog::get($event) ?? abort(404);
        $data = $request->validate([
            '*.sms' => ['nullable', 'string', 'max:1000'],
            '*.subject' => ['nullable', 'string', 'max:191'],
            '*.body' => ['nullable', 'string', 'max:5000'],
            '*.whatsapp_template' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            '*.whatsapp_language' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2}(_[A-Z]{2})?$/'],
        ]);
        $warnings = [];

        foreach (['bn', 'en'] as $locale) {
            $in = $data[$locale] ?? [];
            $default = $config['templates'][$locale] ?? [];
            $rows = [
                'sms' => ['subject' => null, 'body' => $in['sms'] ?? null, 'default' => $default['sms'] ?? null],
                'email' => ['subject' => $in['subject'] ?? null, 'body' => $in['body'] ?? null, 'default' => $default['body'] ?? null],
                'in_app' => ['subject' => $in['subject'] ?? null, 'body' => $in['body'] ?? null, 'default' => $default['body'] ?? null],
            ];
            foreach ($rows as $channel => $row) {
                $same = trim((string) $row['body']) === trim((string) $row['default']) && trim((string) $row['subject']) === trim((string) ($channel === 'sms' ? '' : ($default['subject'] ?? '')));
                if (blank($row['body']) || $same) {
                    MessageTemplate::query()->where(['event' => $event, 'channel' => $channel, 'locale' => $locale])->delete();

                    continue;
                }
                $warnings = array_merge($warnings, $renderer->unknownVariables($event, $row['body'].' '.$row['subject']));
                MessageTemplate::query()->updateOrCreate(['event' => $event, 'channel' => $channel, 'locale' => $locale],
                    ['subject' => $row['subject'] ?: null, 'body' => $row['body']]);
            }
            if (filled($in['whatsapp_template'] ?? null)) {
                MessageTemplate::query()->updateOrCreate(['event' => $event, 'channel' => 'whatsapp', 'locale' => $locale],
                    ['body' => '', 'whatsapp_template' => $in['whatsapp_template'], 'whatsapp_language' => $in['whatsapp_language'] ?: ($locale === 'bn' ? 'bn' : 'en')]);
            } else {
                MessageTemplate::query()->where(['event' => $event, 'channel' => 'whatsapp', 'locale' => $locale])->delete();
            }
        }
        $renderer->forget();

        $redirect = redirect()->route('branch.communication.templates.edit', $event)->with('success', __('Templates saved.'));

        return $warnings ? $redirect->with('warning', __('Unknown variables (they will print empty): :vars', ['vars' => implode(', ', array_map(fn ($v) => '{'.$v.'}', array_unique($warnings)))])) : $redirect;
    }

    public function reset(string $event): RedirectResponse
    {
        $this->authorize('notifications.configure');
        abort_unless(EventCatalog::exists($event), 404);
        MessageTemplate::query()->where('event', $event)->delete();

        return redirect()->route('branch.communication.templates.edit', $event)->with('success', __('The default templates are back.'));
    }
}
