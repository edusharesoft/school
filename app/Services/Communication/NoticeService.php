<?php

namespace App\Services\Communication;

use App\Models\Communication\Notice;
use App\Models\Communication\NoticeRead;
use App\Models\User;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Notice board: drafts, publishing (now or at publish_at), expiry, archive, attachments on the private disk,
 * read receipts, and one-time distribution to the audience through the notification hub
 * (in-app always; SMS / e-mail / WhatsApp when chosen; emergency notices ignore opt-outs).
 */
class NoticeService
{
    public function __construct(protected NotificationHub $hub, protected RecipientResolver $recipients, protected NoticeAudience $audience) {}

    public function save(array $data, ?UploadedFile $file, User $user, ?Notice $notice = null): Notice
    {
        if ($notice && $notice->status === 'archived') {
            throw ValidationException::withMessages(['status' => __('An archived notice cannot be changed.')]);
        }
        $data['audience_filter'] = $this->filter($data['audience'], $data);
        $data['channels'] = array_values(array_intersect((array) ($data['channels'] ?? []), ['sms', 'email', 'whatsapp']));
        $data['is_emergency'] = (bool) ($data['is_emergency'] ?? false) || ($data['category'] ?? null) === 'emergency';

        return DB::transaction(function () use ($data, $file, $user, $notice) {
            $notice ??= new Notice;
            $notice->fill(collect($data)->only($notice->getFillable())->all());
            if (! $notice->exists) {
                $notice->forceFill(['status' => 'draft', 'created_by' => $user->id]);
            }
            if ($file) {
                if ($notice->attachment_path) {
                    Storage::disk('local')->delete($notice->attachment_path);
                }
                $notice->attachment_path = $file->store(tenant()->branch()->storagePath('notices'), 'local');
                $notice->attachment_name = mb_substr($file->getClientOriginalName(), 0, 191);
            }
            $notice->save();

            return $notice;
        });
    }

    /** Publish now, or schedule when publish_at lies in the future. Distribution happens once, when it goes live. */
    public function publish(Notice $notice, User $user): Notice
    {
        if (! in_array($notice->status, ['draft', 'scheduled'], true)) {
            throw ValidationException::withMessages(['status' => __('Only a draft or scheduled notice can be published.')]);
        }
        if ($notice->expires_at && $notice->expires_at->lte(now())) {
            throw ValidationException::withMessages(['expires_at' => __('The expiry time has already passed.')]);
        }
        $notice->forceFill(['published_by' => $user->id]);
        if ($notice->publish_at && $notice->publish_at->isFuture()) {
            $notice->forceFill(['status' => 'scheduled'])->save();

            return $notice;
        }
        $notice->forceFill(['status' => 'published', 'published_at' => now(), 'publish_at' => $notice->publish_at ?? now()])->save();
        $this->distribute($notice, $user);

        return $notice;
    }

    /** Scheduler: publish scheduled notices whose time has come. */
    public function publishDue(): int
    {
        $count = 0;
        foreach (Notice::query()->where('status', 'scheduled')->where('publish_at', '<=', now())->get() as $notice) {
            $notice->forceFill(['status' => 'published', 'published_at' => now()])->save();
            $this->distribute($notice, $notice->publisher);
            $count++;
        }

        return $count;
    }

    public function archive(Notice $notice): Notice
    {
        $notice->forceFill(['status' => 'archived'])->save();

        return $notice;
    }

    public function unschedule(Notice $notice): Notice
    {
        if ($notice->status === 'scheduled') {
            $notice->forceFill(['status' => 'draft'])->save();
        }

        return $notice;
    }

    /** @return array{created:int, queued:int, skipped:int, duplicates:int, no_address:int} */
    public function distribute(Notice $notice, ?User $user = null): array
    {
        if ($notice->distributed_at) {
            return ['created' => 0, 'queued' => 0, 'skipped' => 0, 'duplicates' => 0, 'no_address' => 0];
        }
        $recipients = $this->recipients->audience($notice->audience, (array) $notice->audience_filter);
        $stats = $this->hub->notify('notice.published', $recipients, [
            'title' => $notice->title, 'excerpt' => $notice->excerpt(110), 'category' => __(ucfirst($notice->category)),
        ], $notice, [
            'channels' => array_values(array_unique(['in_app', ...((array) $notice->channels)])),
            'force' => $notice->is_emergency,
            'url' => tenant()->branch()?->url('/notices/'.$notice->id),
            'user' => $user,
        ]);
        $notice->forceFill(['distributed_at' => now()])->save();

        return $stats;
    }

    public function markRead(Notice $notice, User $user): void
    {
        NoticeRead::query()->firstOrCreate(['notice_id' => $notice->id, 'user_id' => $user->id], ['read_at' => now()]);
    }

    /** Live notices a user may read, pinned and emergency first. @return Collection<int,Notice> */
    public function boardFor(User $user, ?string $category = null): Collection
    {
        return Notice::query()->live()->when($category, fn ($q, $c) => $q->where('category', $c))
            ->orderByDesc('is_emergency')->orderByDesc('is_pinned')->orderByDesc('published_at')->limit(300)->get()
            ->filter(fn (Notice $n) => $this->audience->matches($n, $user))->values();
    }

    /** Readers with user accounts in the audience vs. read receipts. */
    public function readStats(Notice $notice): array
    {
        $users = $this->recipients->audience($notice->audience, (array) $notice->audience_filter)
            ->map(fn ($r) => $r->userId)->filter()->unique();

        return ['audience' => $users->count(), 'read' => $notice->reads()->whereIn('user_id', $users->all() ?: [0])->count(), 'total_reads' => $notice->reads()->count()];
    }

    protected function filter(string $audience, array $data): ?array
    {
        return match ($audience) {
            'classes' => ['class_ids' => array_values(array_map('intval', (array) ($data['class_ids'] ?? []))), 'section_ids' => array_values(array_map('intval', (array) ($data['section_ids'] ?? [])))],
            'students_specific' => ['student_ids' => array_values(array_map('intval', (array) ($data['student_ids'] ?? [])))],
            default => null,
        };
    }
}
