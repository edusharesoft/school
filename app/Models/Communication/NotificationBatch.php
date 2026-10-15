<?php

namespace App\Models\Communication;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bulk message composed by staff (sent now or at a scheduled time). */
class NotificationBatch extends Model
{
    use BelongsToBranch;

    protected $fillable = ['title', 'channels', 'audience', 'body_bn', 'body_en', 'recipients', 'scheduled_at', 'is_emergency'];

    protected $guarded = ['created_by'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'audience' => 'array', 'recipients' => 'integer', 'scheduled_at' => 'datetime', 'is_emergency' => 'boolean',
            'created_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NotificationMessage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
