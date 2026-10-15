<?php

namespace App\Models\Reports;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report export asked for by a user (prepared in the background). Not branch-scoped by a global scope on purpose:
 * central users have no branch. Access is always limited to the owner (see scopeOwnedBy) and the file lives on the
 * private disk.
 */
class ExportRun extends Model
{
    public const STATUSES = ['queued', 'running', 'done', 'failed', 'expired'];

    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'filters' => 'array', 'bytes' => 'integer', 'row_count' => 'integer', 'downloads' => 'integer',
            'institution_id' => 'integer', 'branch_id' => 'integer', 'user_id' => 'integer',
            'started_at' => 'datetime', 'finished_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['queued', 'running']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'done' && $this->file_path && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function mime(): string
    {
        return match ($this->format) {
            'csv' => 'text/csv; charset=UTF-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
