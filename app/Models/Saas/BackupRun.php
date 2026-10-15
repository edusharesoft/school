<?php

namespace App\Models\Saas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One backup zip and the result of checking it. Platform-wide (not tenant-bound). */
class BackupRun extends Model
{
    protected $fillable = ['trigger', 'status', 'file_path', 'bytes', 'checksum', 'tables_count', 'rows_count', 'files_count', 'encrypted', 'started_at', 'finished_at',
        'error', 'verified_at', 'verify_status', 'drilled', 'verify_note', 'created_by'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'verified_at' => 'datetime', 'encrypted' => 'boolean', 'drilled' => 'boolean',
            'bytes' => 'integer', 'tables_count' => 'integer', 'rows_count' => 'integer', 'files_count' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeGood($q)
    {
        return $q->where('status', 'done')->whereNotNull('file_path');
    }
}
