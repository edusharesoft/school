<?php

namespace App\Models\Students;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Issued certificate (transfer / character / study). The data column is a snapshot at issue time. */
class Certificate extends Model
{
    use Auditable, BelongsToBranch;

    public const TYPES = ['transfer', 'character', 'study', 'achievement'];

    public const CODES = ['transfer' => 'TC', 'character' => 'CC', 'study' => 'SC', 'achievement' => 'AC'];

    protected $fillable = ['student_id', 'type', 'issued_on', 'data', 'issued_by'];

    protected $guarded = ['serial_no', 'verification_code', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'data' => 'array',
            'revoked_at' => 'datetime',
            'student_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function title(): string
    {
        return __(match ($this->type) {
            'transfer' => 'Transfer Certificate',
            'character' => 'Character Certificate',
            'achievement' => 'Certificate of Achievement',
            default => 'Study Certificate',
        });
    }
}
