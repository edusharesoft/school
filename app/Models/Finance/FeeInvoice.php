<?php

namespace App\Models\Finance;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Totals (subtotal, discount, late fee, total, paid, waived, balance) are maintained by FeeInvoiceService only. */
class FeeInvoice extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['unpaid', 'partial', 'paid', 'cancelled'];

    protected $fillable = [
        'invoice_no', 'student_id', 'student_enrollment_id', 'academic_session_id', 'school_class_id', 'section_id', 'period', 'title',
        'issue_date', 'due_date', 'subtotal', 'discount', 'late_fee', 'total', 'paid', 'waived', 'balance', 'status', 'generated_key', 'notes',
    ];

    protected $guarded = ['late_fee_applied_at', 'cancel_reason', 'cancelled_at', 'cancelled_by', 'created_by'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date', 'late_fee_applied_at' => 'datetime', 'cancelled_at' => 'datetime',
            'subtotal' => 'float', 'discount' => 'float', 'late_fee' => 'float', 'total' => 'float', 'paid' => 'float', 'waived' => 'float', 'balance' => 'float',
            'student_id' => 'integer', 'student_enrollment_id' => 'integer', 'academic_session_id' => 'integer', 'school_class_id' => 'integer',
            'section_id' => 'integer', 'created_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeInvoiceItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeeAllocation::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Unpaid or partly paid (not cancelled). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }

    public function scopeOverdue(Builder $query, ?\DateTimeInterface $on = null): Builder
    {
        return $query->open()->whereDate('due_date', '<', ($on ?? now())->format('Y-m-d'));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['unpaid', 'partial'], true);
    }

    public function tone(): string
    {
        return match ($this->status) { 'paid' => 'success', 'partial' => 'warning', 'cancelled' => 'secondary', default => 'danger' };
    }
}
