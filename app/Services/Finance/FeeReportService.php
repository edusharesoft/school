<?php

namespace App\Services\Finance;

use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeeInvoiceItem;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeRefund;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Fee reports built from recorded receipts and invoices (never from typed totals). */
class FeeReportService
{
    /**
     * Receipts in a date range with totals per day, method, account and collector; refunds are listed separately.
     *
     * @return array{payments:Collection, refunds:Collection, byDay:Collection, byMethod:Collection, byCollector:Collection, total:float, refunded:float, net:float}
     */
    public function collection(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $payments = FeePayment::query()->with(['student:id,name,student_code', 'account:id,code,name', 'receiver:id,name'])
            ->where('status', 'completed')->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->when($filters['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->when($filters['account_id'] ?? null, fn ($q, $a) => $q->where('account_id', $a))
            ->when($filters['received_by'] ?? null, fn ($q, $u) => $q->where('received_by', $u))
            ->orderBy('date')->orderBy('id')->get();
        $refunds = FeeRefund::query()->with('student:id,name,student_code')
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->orderBy('date')->get();

        $total = round($payments->sum('amount'), 2);
        $refunded = round($refunds->sum('amount'), 2);

        return [
            'payments' => $payments,
            'refunds' => $refunds,
            'byDay' => $payments->groupBy(fn ($p) => $p->date->toDateString())->map(fn ($g) => ['count' => $g->count(), 'amount' => round($g->sum('amount'), 2)]),
            'byMethod' => $payments->groupBy('method')->map(fn ($g) => round($g->sum('amount'), 2))->sortDesc(),
            'byCollector' => $payments->groupBy(fn ($p) => $p->receiver?->name ?? '—')->map(fn ($g) => round($g->sum('amount'), 2))->sortDesc(),
            'total' => $total,
            'refunded' => $refunded,
            'net' => round($total - $refunded, 2),
        ];
    }

    /**
     * Outstanding dues per student with aging of the overdue part.
     *
     * @return Collection<int,array{student:mixed, class:?string, section:?string, invoices:int, balance:float, oldest_due:?string, buckets:array<string,float>}>
     */
    public function dues(array $filters = [], ?CarbonImmutable $asOf = null): Collection
    {
        $asOf ??= CarbonImmutable::today();
        $invoices = FeeInvoice::query()->open()->with(['student:id,name,student_code,phone', 'schoolClass:id,name,numeric_order', 'section:id,name'])
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($filters['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
            ->when($filters['overdue'] ?? false, fn ($q) => $q->whereDate('due_date', '<', $asOf->toDateString()))
            ->get();

        return $invoices->groupBy('student_id')->map(function (Collection $rows) use ($asOf) {
            $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
            foreach ($rows as $inv) {
                $days = (int) $inv->due_date->diffInDays($asOf, false);
                $key = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus')));
                $buckets[$key] = round($buckets[$key] + $inv->balance, 2);
            }
            $first = $rows->sortBy('due_date')->first();

            return [
                'student' => $first->student,
                'class' => $first->schoolClass?->name,
                'class_order' => $first->schoolClass?->numeric_order ?? 0,
                'section' => $first->section?->name,
                'invoices' => $rows->count(),
                'balance' => round($rows->sum('balance'), 2),
                'oldest_due' => $first->due_date->toDateString(),
                'buckets' => $buckets,
            ];
        })->sortBy(fn ($r) => sprintf('%05d-%s-%s', $r['class_order'], $r['section'], $r['student']?->name))->values();
    }

    /** Discounts on invoice lines and waivers in a date range. */
    public function discounts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = FeeInvoiceItem::query()->join('fee_invoices', 'fee_invoices.id', '=', 'fee_invoice_items.fee_invoice_id')
            ->where('fee_invoices.branch_id', tenant()->branchId())->where('fee_invoices.status', '!=', 'cancelled')
            ->whereDate('fee_invoices.issue_date', '>=', $from->toDateString())->whereDate('fee_invoices.issue_date', '<=', $to->toDateString())
            ->where('fee_invoice_items.discount', '>', 0)
            ->select('fee_invoice_items.*', 'fee_invoices.invoice_no', 'fee_invoices.student_id', 'fee_invoices.issue_date')->get();

        $byType = [];
        foreach ($items as $item) {
            foreach (array_filter(array_map('trim', explode(',', (string) $item->discount_note))) ?: [__('Discount')] as $label) {
                $byType[$label] = ($byType[$label] ?? 0) + 1;
            }
        }
        $waived = FeeInvoice::query()->where('waived', '>', 0)->whereDate('issue_date', '>=', $from->toDateString())->whereDate('issue_date', '<=', $to->toDateString())
            ->with('student:id,name,student_code')->get();

        return [
            'items' => $items,
            'students' => \App\Models\Students\Student::query()->whereIn('id', $items->pluck('student_id')->merge($waived->pluck('student_id'))->unique())->get(['id', 'name', 'student_code'])->keyBy('id'),
            'discountTotal' => round($items->sum('discount'), 2),
            'byType' => $byType,
            'waivers' => $waived,
            'waivedTotal' => round($waived->sum('waived'), 2),
        ];
    }

    /** Billed (invoices issued) vs collected (receipts) per month, last $months months. */
    public function monthly(int $months = 12): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths($months - 1);
        $billed = FeeInvoice::query()->where('status', '!=', 'cancelled')->whereDate('issue_date', '>=', $start->toDateString())
            ->get(['issue_date', 'total'])->groupBy(fn ($i) => $i->issue_date->format('Y-m'))->map(fn ($g) => round($g->sum('total'), 2));
        $collected = FeePayment::query()->where('status', 'completed')->whereDate('date', '>=', $start->toDateString())
            ->get(['date', 'amount'])->groupBy(fn ($p) => $p->date->format('Y-m'))->map(fn ($g) => round($g->sum('amount'), 2));

        $labels = [];
        $b = [];
        $c = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->addMonths($i);
            $labels[] = $m->format('M Y');
            $b[] = $billed[$m->format('Y-m')] ?? 0;
            $c[] = $collected[$m->format('Y-m')] ?? 0;
        }

        return ['labels' => $labels, 'billed' => $b, 'collected' => $c];
    }
}
