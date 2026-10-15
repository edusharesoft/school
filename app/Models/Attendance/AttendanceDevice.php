<?php

namespace App\Models\Attendance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A biometric / QR / RFID terminal (or the vendor middleware in front of it) that pushes punches to
 * POST /api/v1/attendance/punches with header "X-Device-Key". Only the SHA-256 hash of the key is stored.
 */
class AttendanceDevice extends Model
{
    use Auditable, BelongsToBranch;

    public const TYPES = ['biometric', 'qr_terminal', 'rfid'];

    protected $fillable = ['name', 'serial_no', 'type', 'is_active'];

    protected $hidden = ['api_key_hash'];

    protected array $auditExclude = ['api_key_hash', 'last_seen_at', 'last_ip'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    /** Generate and store a new key; returns the plain key (shown to the admin exactly once). */
    public function issueKey(): string
    {
        $key = 'dev_'.Str::random(40);
        $this->forceFill(['api_key_hash' => hash('sha256', $key), 'api_key_hint' => substr($key, -6)]);

        return $key;
    }

    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
