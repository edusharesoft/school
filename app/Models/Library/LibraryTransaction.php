<?php

namespace App\Models\Library;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An issue of one copy to a student or an employee, until it is returned or written off as lost. */
class LibraryTransaction extends Model
{
    use Auditable, BelongsToBranch;

    public const MEMBER_TYPES = ['student', 'employee'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date', 'due_on' => 'date', 'returned_on' => 'date', 'overdue_notified_on' => 'date', 'renewals' => 'integer',
            'member_id' => 'integer', 'library_book_id' => 'integer', 'library_book_copy_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(LibraryBookCopy::class, 'library_book_copy_id')->withTrashed();
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id')->withTrashed();
    }

    public function fines(): HasMany
    {
        return $this->hasMany(LibraryFine::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** The student or employee (attach with LibraryMembers::attach() when listing many rows). */
    public function memberModel(): Student|Employee|null
    {
        if (! $this->relationLoaded('memberModel')) {
            $this->setRelation('memberModel', \App\Services\Library\LibraryMembers::find($this->member_type, (int) $this->member_id));
        }

        return $this->getRelation('memberModel');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }

    public function scopeOverdue(Builder $query, ?\DateTimeInterface $on = null): Builder
    {
        return $query->where('status', 'issued')->whereDate('due_on', '<', ($on ?? now())->format('Y-m-d'));
    }

    public function scopeForMember(Builder $query, string $type, int $id): Builder
    {
        return $query->where('member_type', $type)->where('member_id', $id);
    }

    public function isOverdue(?\DateTimeInterface $on = null): bool
    {
        return $this->status === 'issued' && $this->due_on->lt(\Illuminate\Support\Carbon::parse($on ?? now())->startOfDay());
    }
}
