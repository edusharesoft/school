<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationMessage;
use App\Models\Communication\NotificationBatch;
use App\Models\Communication\NotificationMessage;
use App\Models\Communication\NotificationOptOut;
use App\Models\User;
use App\Services\Notifications\Sms\AlphaSmsGateway;
use App\Services\Notifications\Sms\BulkSmsBdGateway;
use App\Services\Notifications\Sms\LogSmsGateway;
use App\Services\Notifications\Sms\ShatajSmsGateway;
use App\Services\Notifications\Sms\SmsGateway;
use App\Services\Notifications\Sms\SslWirelessGateway;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The single entry point for SMS, e-mail, WhatsApp and in-app messages.
 *
 *   notify()   renders the template for every recipient × channel, applies opt-outs and de-duplication, records one
 *              notification_messages row each and queues the external ones (SendNotificationMessage job)
 *   process()  sends one queued message through its provider (claimed atomically, so a duplicate job is harmless),
 *              retrying transient failures with back-off up to notifications.max_attempts
 *
 * Business code only calls notify(); providers can be changed per branch without touching it.
 */
class NotificationHub
{
    public function __construct(protected NotificationSettings $settings, protected TemplateRenderer $renderer) {}

    /**
     * @param  iterable<Recipient>  $recipients
     * @param  array{channels?:string[], force?:bool, scheduled_at?:?CarbonInterface, batch?:?NotificationBatch, user?:?User,
     *               url?:?string, bodies?:array<string,string>, dedupe?:bool, dedupe_suffix?:string}  $opts
     * @return array{created:int, queued:int, skipped:int, duplicates:int, no_address:int}
     */
    public function notify(string $event, iterable $recipients, array $vars = [], ?Model $source = null, array $opts = []): array
    {
        $stats = ['created' => 0, 'queued' => 0, 'skipped' => 0, 'duplicates' => 0, 'no_address' => 0];
        if (! EventCatalog::exists($event)) {
            throw new \InvalidArgumentException("Unknown notification event [$event].");
        }

        $enabled = $this->settings->enabledChannels();
        $channels = array_values(array_intersect($opts['channels'] ?? $this->settings->eventChannels($event), $enabled));
        if ($channels === []) {
            return $stats;
        }

        $batch = $opts['batch'] ?? null;
        $scheduledAt = $opts['scheduled_at'] ?? null;
        $scheduled = $scheduledAt && $scheduledAt->isFuture();
        $force = (bool) ($opts['force'] ?? false);
        $optOuts = $force ? collect() : NotificationOptOut::query()->whereIn('channel', $channels)->get()->map(fn ($o) => $o->channel.'|'.$o->address)->flip();
        $base = ['branch' => tenant()->branch()?->displayName(), 'url' => $opts['url'] ?? null] + $vars;
        $queued = [];

        foreach ($recipients as $recipient) {
            foreach ($channels as $channel) {
                $to = $this->address($recipient, $channel);
                if ($to === null) {
                    $stats['no_address']++;

                    continue;
                }
                $locale = in_array($recipient->locale, ['bn', 'en'], true) ? $recipient->locale : $this->settings->locale();
                $data = $base + $recipient->vars + ['name' => $recipient->name];
                if (isset($opts['bodies'])) {
                    $data['body'] = $opts['bodies'][$locale] ?? collect($opts['bodies'])->filter()->first() ?? '';
                }
                $template = $this->renderer->template($event, $channel, $locale);

                $attributes = [
                    'notification_batch_id' => $batch?->id,
                    'event' => $event,
                    'channel' => $channel,
                    'recipient_type' => $recipient->type,
                    'recipient_id' => $recipient->id,
                    'recipient_name' => mb_substr($recipient->name, 0, 150),
                    'user_id' => $channel === 'in_app' ? $recipient->userId : null,
                    'to' => $to,
                    'locale' => $locale,
                    'subject' => mb_substr($this->renderer->fill($template['subject'], $data), 0, 191) ?: null,
                    'body' => $this->renderer->fill($template['body'], $data),
                    'url' => $data['url'] ? mb_substr((string) $data['url'], 0, 255) : null,
                    'source_type' => $source?->getMorphClass(),
                    'source_id' => $source?->getKey(),
                    'dedupe_key' => ($opts['dedupe'] ?? true) ? $this->dedupeKey($event, $source, $batch, $recipient, $channel, (string) ($opts['dedupe_suffix'] ?? '')) : null,
                    'scheduled_at' => $scheduled ? $scheduledAt : null,
                    'created_by' => ($opts['user'] ?? null)?->id,
                ];
                if ($channel === 'sms') {
                    $attributes['segments'] = SmsCounter::segments($attributes['body']);
                }

                [$status, $error] = ['queued', null];
                if ($channel === 'whatsapp') {
                    if (! $template['whatsapp_template']) {
                        [$status, $error] = ['skipped', __('No approved WhatsApp template is set for this event.')];
                    }
                    $attributes['payload'] = [
                        'template' => $template['whatsapp_template'],
                        'language' => $template['whatsapp_language'],
                        'parameters' => array_map(fn ($var) => (string) ($data[$var] ?? ''), (array) EventCatalog::get($event, 'whatsapp_params', [])),
                    ];
                }
                if ($channel !== 'in_app' && $optOuts->has($channel.'|'.$to)) {
                    [$status, $error] = ['skipped', __('The recipient opted out of this channel.')];
                }
                if ($status === 'queued' && $scheduled) {
                    $status = 'scheduled';
                }
                if ($status === 'queued' && $channel === 'in_app') {
                    $status = 'delivered';
                }

                $message = $this->record($attributes, $status, $error);
                if (! $message) {
                    $stats['duplicates']++;

                    continue;
                }
                $stats['created']++;
                if ($status === 'skipped') {
                    $stats['skipped']++;
                } elseif ($status === 'queued') {
                    $stats['queued']++;
                    $queued[] = $message->id;
                }
            }
        }

        foreach ($queued as $id) {
            SendNotificationMessage::dispatch($id)->afterCommit();
        }

        return $stats;
    }

    /**
     * Send one message. Returns 'sent', 'failed', 'retry' (transient error, try again later) or null if the message
     * was not waiting to be sent (already handled by another worker, cancelled …).
     */
    public function process(int $messageId): ?string
    {
        $claimed = NotificationMessage::query()->whereKey($messageId)->where('status', 'queued')
            ->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        if (! $claimed) {
            return null;
        }
        $message = NotificationMessage::query()->findOrFail($messageId);

        try {
            $result = $this->deliver($message);
        } catch (\Throwable $e) {
            report($e);
            $result = SendResult::failed(class_basename($e).': '.$e->getMessage(), true);
        }

        if ($result->ok) {
            $message->forceFill([
                'status' => $result->status, 'provider' => $this->providerName($message->channel), 'provider_message_id' => $result->providerId ?: null,
                'error' => null, 'sent_at' => now(), 'delivered_at' => $result->status === 'delivered' ? now() : null,
            ])->save();

            return 'sent';
        }

        $retry = $result->retryable && $message->attempts < (int) config('notifications.max_attempts', 3);
        $message->forceFill(['status' => $retry ? 'queued' : 'failed', 'provider' => $this->providerName($message->channel), 'error' => $result->error])->save();

        return $retry ? 'retry' : 'failed';
    }

    public function deliver(NotificationMessage $message): SendResult
    {
        return match ($message->channel) {
            'sms' => $this->smsGateway()->send($message->to, $message->body, $this->settings->smsConfig()),
            'email' => app(EmailSender::class)->send($message),
            'whatsapp' => app(WhatsAppCloudGateway::class)->sendTemplate($message->to, (string) ($message->payload['template'] ?? ''),
                (string) ($message->payload['language'] ?? 'bn'), (array) ($message->payload['parameters'] ?? []), $this->settings->whatsappConfig()),
            'in_app' => SendResult::sent(null, 'delivered'),
            default => SendResult::failed('Unknown channel '.$message->channel),
        };
    }

    /** Failed (or cancelled) message → queued again with a fresh attempt counter. */
    public function retry(NotificationMessage $message): bool
    {
        if (! in_array($message->status, ['failed', 'cancelled'], true) || $message->channel === 'in_app') {
            return false;
        }
        $message->forceFill(['status' => 'queued', 'attempts' => 0, 'error' => null])->save();
        SendNotificationMessage::dispatch($message->id)->afterCommit();

        return true;
    }

    public function cancel(NotificationMessage $message): bool
    {
        return (bool) NotificationMessage::query()->whereKey($message->id)->whereIn('status', ['scheduled', 'queued'])->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    /**
     * Scheduler hook (every minute, per branch): release scheduled messages that are due, and re-queue messages
     * stuck in "queued" for more than 20 minutes (lost job, or waiting for a retry on the sync queue).
     */
    public function dispatchDue(): int
    {
        $count = 0;
        NotificationMessage::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->where('channel', 'in_app')
            ->update(['status' => 'delivered', 'sent_at' => now(), 'delivered_at' => now(), 'updated_at' => now()]);

        $due = NotificationMessage::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->pluck('id');
        NotificationMessage::query()->whereIn('id', $due)->where('status', 'scheduled')->update(['status' => 'queued', 'updated_at' => now()]);
        $stuck = NotificationMessage::query()->where('status', 'queued')->where('updated_at', '<', now()->subMinutes(20))->limit(500)->pluck('id');

        foreach ($due->merge($stuck)->unique() as $id) {
            SendNotificationMessage::dispatch((int) $id);
            $count++;
        }

        return $count;
    }

    /**
     * Shataj SMS hands messages to a phone, which sends them a little later: ask for the final state of recent
     * messages (sent → delivered / failed; never backwards). Runs with notifications:dispatch every minute.
     */
    public function syncSmsDelivery(int $limit = 30): int
    {
        if ($this->settings->smsDriver() !== 'shataj_sms') {
            return 0;
        }
        $gateway = app(ShatajSmsGateway::class);
        $config = $this->settings->smsConfig();
        $updated = 0;
        NotificationMessage::query()->where('channel', 'sms')->where('provider', 'shataj_sms')->where('status', 'sent')->whereNotNull('provider_message_id')
            ->where('sent_at', '>=', now()->subDays(2))->where('sent_at', '<=', now()->subMinute())
            ->orderBy('updated_at')->limit($limit)->get()
            ->each(function (NotificationMessage $message) use ($gateway, $config, &$updated) {
                $state = $gateway->fetch($message->provider_message_id, $config);
                match ($state['status'] ?? null) {
                    'delivered' => $message->forceFill(['status' => 'delivered', 'delivered_at' => now()]),
                    'failed' => $message->forceFill(['status' => 'failed', 'error' => 'Shataj SMS: the phone could not send the message']),
                    default => $message->forceFill(['updated_at' => now()]),   // checked; look at the others first next time
                };
                $updated += (int) in_array($message->status, ['delivered', 'failed'], true);
                $message->save();
            });

        return $updated;
    }

    public function smsGateway(): SmsGateway
    {
        return match ($this->settings->smsDriver()) {
            'shataj_sms' => app(ShatajSmsGateway::class),
            'ssl_wireless' => app(SslWirelessGateway::class),
            'bulksmsbd' => app(BulkSmsBdGateway::class),
            'alpha_sms' => app(AlphaSmsGateway::class),
            default => app(LogSmsGateway::class),
        };
    }

    protected function providerName(string $channel): string
    {
        return match ($channel) {
            'sms' => $this->settings->smsDriver(),
            'email' => $this->settings->smtp() ? 'smtp' : (string) config('mail.default'),
            'whatsapp' => 'whatsapp_cloud',
            default => 'in_app',
        };
    }

    protected function address(Recipient $recipient, string $channel): ?string
    {
        return match ($channel) {
            'in_app' => $recipient->userId ? 'user:'.$recipient->userId : null,
            'sms', 'whatsapp' => PhoneNumber::normalize($recipient->phone),
            'email' => filter_var($recipient->email, FILTER_VALIDATE_EMAIL) ? mb_strtolower(trim($recipient->email)) : null,
            default => null,
        };
    }

    protected function dedupeKey(string $event, ?Model $source, ?NotificationBatch $batch, Recipient $recipient, string $channel, string $suffix = ''): ?string
    {
        if (! $source && ! $batch) {
            return null;   // ad-hoc messages are not de-duplicated
        }
        $origin = $batch ? 'batch:'.$batch->id : $source->getMorphClass().':'.$source->getKey();

        return hash('sha256', implode('|', [$event, $origin, $recipient->key(), $recipient->vars['student_name'] ?? '', $channel, $suffix]));
    }

    protected function record(array $attributes, string $status, ?string $error): ?NotificationMessage
    {
        if ($attributes['dedupe_key'] && NotificationMessage::query()->where('dedupe_key', $attributes['dedupe_key'])->exists()) {
            return null;
        }
        $message = new NotificationMessage($attributes);
        $message->forceFill(['status' => $status, 'error' => $error] + ($status === 'delivered' ? ['sent_at' => now(), 'delivered_at' => now(), 'provider' => 'in_app'] : []));

        try {
            $message->save();
        } catch (UniqueConstraintViolationException) {
            return null;   // the same message was recorded concurrently
        }

        return $message;
    }
}
