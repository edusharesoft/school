<?php

namespace App\Models\Library;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One physical copy: its own accession number, barcode and status. */
class LibraryBookCopy extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const STATUSES = ['available', 'issued', 'reserved', 'lost', 'damaged', 'withdrawn'];

    protected $fillable = ['acquired_on', 'price', 'note'];

    protected function casts(): array
    {
        return ['acquired_on' => 'date', 'price' => 'decimal:2', 'library_book_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'library_book_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LibraryTransaction::class);
    }

    public function value(): float
    {
        return (float) ($this->price ?? $this->book?->price ?? 0);
    }

    public function tone(): string
    {
        return ['available' => 'success', 'issued' => 'primary', 'reserved' => 'info', 'lost' => 'danger', 'damaged' => 'warning', 'withdrawn' => 'secondary'][$this->status] ?? 'secondary';
    }
}
