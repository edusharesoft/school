<?php

namespace App\Models\Communication;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Read receipt: the first time a user opened a notice. Scoped through its notice. */
class NoticeRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['notice_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'notice_id' => 'integer', 'user_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
