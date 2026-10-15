<?php

namespace App\Models\Library;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A place in the queue for a title. "ready" = a returned copy is being held for the member until ready_until. */
class LibraryReservation extends Model
{
    use Auditable, BelongsToBranch;

    public const ACTIVE = ['waiting', 'ready'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reserved_on' => 'date', 'ready_until' => 'date', 'member_id' => 'integer', 'library_book_id' => 'integer', 'library_book_copy_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id')->withTrashed();
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(LibraryBookCopy::class, 'library_book_copy_id')->withTrashed();
    }

    public function memberModel(): Student|Employee|null
    {
        if (! $this->relationLoaded('memberModel')) {
            $this->setRelation('memberModel', \App\Services\Library\LibraryMembers::find($this->member_type, (int) $this->member_id));
        }

        return $this->getRelation('memberModel');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}
