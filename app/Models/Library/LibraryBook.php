<?php

namespace App\Models\Library;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A title in the catalogue. Physical copies live in LibraryBookCopy. */
class LibraryBook extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    protected $fillable = ['library_category_id', 'title', 'title_bn', 'isbn', 'authors', 'publisher', 'edition', 'language', 'published_year', 'pages', 'price', 'shelf', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2', 'is_active' => 'boolean', 'published_year' => 'integer', 'pages' => 'integer', 'library_category_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'library_category_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(LibraryBookCopy::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(LibraryReservation::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/[^0-9Xx]/', '', $term);

        return $query->where(function ($q) use ($term, $digits) {
            $q->where('title', 'like', "%$term%")->orWhere('title_bn', 'like', "%$term%")->orWhere('authors', 'like', "%$term%")->orWhere('publisher', 'like', "%$term%");
            if (strlen($digits) >= 4) {
                $q->orWhere('isbn', 'like', "%$digits%");
            }
        });
    }

    public function displayTitle(): string
    {
        return app()->getLocale() === 'bn' && $this->title_bn ? $this->title_bn : $this->title;
    }

    /** ISBN-10 / ISBN-13 check (hyphens and spaces ignored). */
    public static function validIsbn(?string $isbn): bool
    {
        $s = strtoupper(preg_replace('/[\s-]/', '', (string) $isbn));
        if (preg_match('/^\d{13}$/', $s)) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) $s[$i] * ($i % 2 ? 3 : 1);
            }

            return (10 - $sum % 10) % 10 === (int) $s[12];
        }
        if (preg_match('/^\d{9}[\dX]$/', $s)) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $sum += ($s[$i] === 'X' ? 10 : (int) $s[$i]) * (10 - $i);
            }

            return $sum % 11 === 0;
        }

        return false;
    }
}
