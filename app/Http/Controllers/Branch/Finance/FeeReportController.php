<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Finance\Account;
use App\Models\Finance\FeePayment;
use App\Models\User;
use App\Services\Finance\FeeReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Collection, outstanding dues and discount reports (screen, print and CSV). */
class FeeReportController extends Controller
{
    public function __construct(protected FeeReportService $reports) {}

    public function collection(Request $request): View|StreamedResponse
    {
        $this->authorize('financial-reports.view');
        [$from, $to] = $this->range($request);
        $data = $this->reports->collection($from, $to, $request->only(['method', 'account_id', 'received_by']));

        if ($request->input('export') === 'csv') {
            $this->authorize('financial-reports.export');

            return $this->csv('collection-'.$from->toDateString().'-'.$to->toDateString(), ['Date', 'Receipt', 'Student ID', 'Student', 'Method', 'Account', 'Reference', 'Amount', 'Collected by'],
                $data['payments']->map(fn ($p) => [$p->date->toDateString(), $p->receipt_no, $p->student?->student_code, $p->student?->name, $p->method, $p->account?->name, $p->reference, $p->amount, $p->receiver?->name]));
        }

        return view('branch.finance.reports.collection', $data + [
            'from' => $from, 'to' => $to,
            'accounts' => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
            'collectors' => User::query()->whereIn('id', FeePayment::query()->select('received_by')->distinct())->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function dues(Request $request): View|StreamedResponse
    {
        $this->authorize('financial-reports.view');
        $filters = ['class_id' => $request->integer('class_id') ?: null, 'section_id' => $request->integer('section_id') ?: null, 'overdue' => $request->boolean('overdue')];
        $rows = $this->reports->dues($filters);

        if ($request->input('export') === 'csv') {
            $this->authorize('financial-reports.export');

            return $this->csv('outstanding-dues-'.now()->toDateString(), ['Student ID', 'Student', 'Mobile', 'Class', 'Section', 'Invoices', 'Oldest due', 'Not yet due', '1-30', '31-60', '61-90', '90+', 'Total due'],
                $rows->map(fn ($r) => [$r['student']?->student_code, $r['student']?->name, $r['student']?->phone, $r['class'], $r['section'], $r['invoices'], $r['oldest_due'],
                    $r['buckets']['current'], $r['buckets']['1_30'], $r['buckets']['31_60'], $r['buckets']['61_90'], $r['buckets']['90_plus'], $r['balance']]));
        }

        return view('branch.finance.reports.dues', [
            'rows' => $rows, 'filters' => $filters,
            'classes' => SchoolClass::query()->active()->ordered()->pluck('name', 'id'),
            'sections' => $filters['class_id'] ? Section::query()->where('school_class_id', $filters['class_id'])->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function discounts(Request $request): View
    {
        $this->authorize('financial-reports.view');
        [$from, $to] = $this->range($request, true);

        return view('branch.finance.reports.discounts', $this->reports->discounts($from, $to) + ['from' => $from, 'to' => $to]);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    protected function range(Request $request, bool $monthDefault = false): array
    {
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : ($monthDefault ? CarbonImmutable::today()->startOfMonth() : CarbonImmutable::today());
        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->subYear();
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    protected function csv(string $name, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla correctly
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, (array) $row)); // CSV injection guard
            }
            fclose($out);
        }, Str::slug($name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
