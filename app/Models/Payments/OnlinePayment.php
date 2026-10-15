<?php

namespace App\Models\Payments;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One online checkout attempt. Only OnlinePaymentService changes its status. */
class OnlinePayment extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['initiated', 'pending', 'paid', 'failed', 'cancelled', 'expired', 'review', 'refunded'];

    /** Statuses in which the money may still arrive, so the gateway is asked again. */
    public const OPEN = ['initiated', 'pending'];

    protected $fillable = ['gateway', 'tran_id', 'student_id', 'invoice_ids', 'amount', 'currency', 'payer_user_id', 'payer_ip', 'expires_at'];

    protected $guarded = ['status', 'gateway_ref', 'gateway_txn_id', 'paid_amount', 'payment_method', 'fee_payment_id', 'failure_reason',
        'refunded_amount', 'refund_ref', 'meta', 'verified_at', 'paid_at'];

    protected array $auditExclude = ['meta'];

    protected function casts(): array
    {
        return [
            'invoice_ids' => 'array', 'meta' => 'array', 'amount' => 'float', 'paid_amount' => 'float', 'refunded_amount' => 'float',
            'student_id' => 'integer', 'fee_payment_id' => 'integer', 'payer_user_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
            'verified_at' => 'datetime', 'paid_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feePayment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OnlinePaymentEvent::class)->orderBy('id');
    }

    /** @return Collection<int,FeeInvoice> */
    public function invoices(): Collection
    {
        return FeeInvoice::query()->whereIn('id', $this->invoice_ids ?: [0])->orderBy('due_date')->get();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function tone(): string
    {
        return match ($this->status) {
            'paid' => 'success', 'initiated', 'pending' => 'warning', 'review' => 'danger', 'refunded' => 'info', default => 'secondary',
        };
    }
}
