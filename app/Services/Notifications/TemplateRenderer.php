<?php

namespace App\Services\Notifications;

use App\Models\Communication\MessageTemplate;
use Illuminate\Support\Collection;

/**
 * Picks the template for event × channel × locale (branch override, else config default) and fills {variables}.
 * SMS uses the short "sms" text; e-mail and in-app use "subject" + "body".
 */
class TemplateRenderer
{
    /** @var array<int,Collection<string,MessageTemplate>> per branch — a queue worker serves many branches */
    protected array $overrides = [];

    /** @return array{subject:?string, body:string, whatsapp_template:?string, whatsapp_language:?string} */
    public function template(string $event, string $channel, string $locale): array
    {
        $override = $this->overrides()->get("$event|$channel|$locale");
        $default = EventCatalog::get($event, "templates.$locale") ?? EventCatalog::get($event, 'templates.bn') ?? [];

        return [
            'subject' => $override?->subject ?? ($default['subject'] ?? null),
            'body' => $override?->body ?? ($channel === 'sms' ? ($default['sms'] ?? '') : ($default['body'] ?? '')),
            'whatsapp_template' => $override?->whatsapp_template,
            'whatsapp_language' => $override?->whatsapp_language ?: ($locale === 'bn' ? 'bn' : 'en'),
        ];
    }

    public function fill(?string $text, array $vars): string
    {
        return trim(preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : '', (string) $text));
    }

    /** Variables in a template that the event does not know (shown as a warning in the editor). */
    public function unknownVariables(string $event, ?string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', (string) $text, $m);

        return array_values(array_diff(array_unique($m[1]), (array) EventCatalog::get($event, 'vars', [])));
    }

    public function forget(): void
    {
        $this->overrides = [];
    }

    protected function overrides(): Collection
    {
        return $this->overrides[(int) tenant()->branchId()] ??= MessageTemplate::query()->get()->keyBy(fn ($t) => "$t->event|$t->channel|$t->locale");
    }
}
