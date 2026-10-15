<?php

namespace App\Models\Students;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use Auditable, BelongsToBranch, HasFactory, SoftDeletes;

    public const RELATIONS = ['father', 'mother', 'brother', 'sister', 'uncle', 'aunt', 'grandparent', 'other'];

    protected $fillable = ['name', 'name_bn', 'phone', 'alt_phone', 'email', 'occupation', 'nid_number', 'address'];

    /** Restricted identity number — never serialised. */
    protected $hidden = ['nid_number'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'user_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->withPivot(['relation', 'is_primary', 'is_emergency_contact'])
            ->withTimestamps();
    }
}
