<?php

namespace App\Services\Reports\Definitions\Library;

use App\Models\Library\LibraryBook;
use App\Models\Library\LibraryCategory;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;

/** The book catalogue with copies in each state and the stock value. */
class CatalogueReport extends Report
{
    use Lookups;

    public function key(): string { return 'library.catalogue'; }

    public function title(): string { return __('Book catalogue and stock'); }

    public function description(): string { return __('Titles with the number of copies available, issued, lost or damaged, and the value of the stock.'); }

    public function permission(): string { return 'library.view'; }

    public function filters(): array
    {
        return [['name' => 'category', 'type' => 'select', 'label' => 'Category', 'options' => fn () => LibraryCategory::query()->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'q', 'type' => 'text', 'label' => 'Search title / author / ISBN']];
    }

    public function run(array $filters): R
    {
        $books = LibraryBook::query()->with('category:id,name')->withCount([
            'copies as copies_total' => fn ($q) => $q->where('status', '!=', 'withdrawn'),
            'copies as copies_available' => fn ($q) => $q->where('status', 'available'),
            'copies as copies_issued' => fn ($q) => $q->where('status', 'issued'),
            'copies as copies_lost' => fn ($q) => $q->whereIn('status', ['lost', 'damaged']),
        ])->where('is_active', true)
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('library_category_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->search($v))
            ->orderBy('title')->get();

        $rows = $books->map(fn (LibraryBook $b) => ['title' => $b->displayTitle(), 'authors' => $b->authors, 'isbn' => $b->isbn, 'category' => $b->category?->name, 'publisher' => $b->publisher,
            'shelf' => $b->shelf, 'total' => $b->copies_total, 'available' => $b->copies_available, 'issued' => $b->copies_issued, 'lost' => $b->copies_lost,
            'value' => round($b->copies_total * (float) $b->price, 2)])->all();

        return $this->result([R::col('title', __('Title')), R::col('authors', __('Author')), R::col('isbn', 'ISBN'), R::col('category', __('Category')), R::col('publisher', __('Publisher')),
            R::col('shelf', __('Shelf')), R::col('total', __('Copies'), 'int', true), R::col('available', __('Available'), 'int', true), R::col('issued', __('Issued'), 'int', true),
            R::col('lost', __('Lost / damaged'), 'int', true), R::col('value', __('Value'), 'money', true)], $rows);
    }
}
