<?php

namespace App\Services\Reports\Definitions\Library;

use App\Models\Library\LibraryTransaction;
use App\Services\Reports\Definitions\Concerns\Lookups;
use App\Services\Reports\Report;
use App\Services\Reports\ReportResult as R;
use Illuminate\Support\Facades\DB;

/** The most borrowed titles of a period. */
class MostIssuedReport extends Report
{
    use Lookups;

    public function key(): string { return 'library.most-issued'; }

    public function title(): string { return __('Most borrowed books'); }

    public function description(): string { return __('Titles ranked by how often they were issued in a period.'); }

    public function permission(): string { return 'library.view'; }

    public function filters(): array
    {
        return [...$this->periodFilters(), ['name' => 'limit', 'type' => 'select', 'label' => 'Show', 'options' => [10 => __('Top 10'), 25 => __('Top 25'), 50 => __('Top 50'), 100 => __('Top 100')], 'default' => 25]];
    }

    public function run(array $filters): R
    {
        [$from, $to] = $this->range($filters);
        $limit = (int) ($filters['limit'] ?? 25) ?: 25;
        $top = LibraryTransaction::query()->toBase()->whereDate('issued_on', '>=', $from->toDateString())->whereDate('issued_on', '<=', $to->toDateString())
            ->select('library_book_id', DB::raw('COUNT(*) as loans'))
            ->groupBy('library_book_id')->orderByDesc('loans')->orderBy('library_book_id')->limit($limit)->get();
        $borrowers = LibraryTransaction::query()->toBase()->whereIn('library_book_id', $top->pluck('library_book_id'))
            ->whereDate('issued_on', '>=', $from->toDateString())->whereDate('issued_on', '<=', $to->toDateString())
            ->select('library_book_id', 'member_type', 'member_id')->distinct()->get()->groupBy('library_book_id')->map->count();
        $books = \App\Models\Library\LibraryBook::query()->with('category:id,name')->whereIn('id', $top->pluck('library_book_id'))->get()->keyBy('id');

        $rows = [];
        foreach ($top as $i => $t) {
            $b = $books->get($t->library_book_id);
            $rows[] = ['rank' => $i + 1, 'title' => $b?->displayTitle(), 'authors' => $b?->authors, 'category' => $b?->category?->name, 'loans' => (int) $t->loans, 'borrowers' => (int) ($borrowers[$t->library_book_id] ?? 0)];
        }

        return $this->result([R::col('rank', '#', 'int'), R::col('title', __('Title')), R::col('authors', __('Author')), R::col('category', __('Category')),
            R::col('loans', __('Times issued'), 'int', true), R::col('borrowers', __('Different borrowers'), 'int')], $rows);
    }
}
