<?php

namespace App\Models\Communication;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** Branch override of a default template from config/notifications.php (one per event × channel × locale). */
class MessageTemplate extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['event', 'channel', 'locale', 'subject', 'body', 'whatsapp_template', 'whatsapp_language'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer'];
    }
}
