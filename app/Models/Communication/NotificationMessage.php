<?php

namespace App\Models\Communication;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One message to one recipient on one channel: the outbox, the delivery history and (channel in_app) the user's inbox.
 * Rows are never edited after sending except for delivery status updates.
 */
class NotificationMessage extends Model
{
    use BelongsToBranch;

    public const STATUSES = ['scheduled', 'queued', 'sending', 'sent', 'delivered', 'failed', 'skipped', 'cancelled'];

    protected $fillable = [
        'notification_batch_id', 'event', 'channel', 'recipient_type', 'recipient_id', 'recipient_name', 'user_id', 'to', 'locale',
        'subject', 'body', 'payload', 'url', 'segments', 'source_type', 'source_id', 'dedupe_key', 'scheduled_at', 'created_by',
    ];

    protected $guarded = ['status', 'provider', 'provider_message_id', 'error', 'attempts', 'sent_at', 'delivered_at', 'read_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'attempts' => 'integer', 'segments' => 'integer', 'recipient_id' => 'integer', 'user_id' => 'integer',
            'notification_batch_id' => 'integer', 'created_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
            'scheduled_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'notification_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** In-app messages of a user (the inbox). */
    public function scopeInbox(Builder $query, User $user): Builder
    {
        return $query->where('channel', 'in_app')->where('user_id', $user->id)->whereNotIn('status', ['scheduled', 'cancelled']);
    }

    public function tone(): string
    {
        return match ($this->status) {
            'delivered', 'sent' => 'success', 'failed' => 'danger', 'queued', 'sending', 'scheduled' => 'warning', default => 'secondary',
        };
    }
}
