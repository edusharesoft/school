<?php

namespace App\Services\Operations;

use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FeeInvoiceService;
use App\Services\Finance\FinanceSettings;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Monthly charges of the optional services (transport, hostel) are normal fee invoices of the student, so they appear
 * in the same dues, receipts, ledger and portal as the school fees. One invoice per service allocation and month
 * (generated_key), so generating twice never bills twice.
 */
class ServiceFees
{
    public function __construct(protected FeeInvoiceService $invoices, protected FinanceSettings $settings, protected ChartOfAccounts $chart) {}

    /** The fee head of a service category; created (with its own income account) the first time it is needed. */
    public function head(string $category, string $name, string $nameBn): FeeHead
    {
        $head = FeeHead::query()->where('category', $category)->where('is_active', true)->orderBy('id')->first();
        if ($head) {
            return $head;
        }
        $this->chart->ensure();

        return FeeHead::query()->create([
            'name' => $name, 'name_bn' => $nameBn, 'code' => strtoupper($category), 'category' => $category,
            'income_account_id' => $this->chart->incomeAccountForHead($name, $nameBn)->id, 'is_active' => true, 'sort_order' => 50,
        ]);
    }

    public static function period(string $period): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => __('The month must look like 2026-07.')]);
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
    }

    /**
     * @param  array<int,array{fee_head_id:int, description:string, amount:float}>  $items
     * @return FeeInvoice|null null when this key was already billed
     */
    public function bill(Student $student, array $items, string $title, string $period, string $key, ?User $user = null): ?FeeInvoice
    {
        if (FeeInvoice::query()->where('generated_key', $key)->exists()) {
            return null;
        }
        $start = self::period($period);
        $due = $start->setDay(min(max(1, (int) $this->settings->get('due_day')), $start->daysInMonth));

        return $this->invoices->create($student, null, $items, $title, $start, $due, $period, $key, $user);
    }
}
