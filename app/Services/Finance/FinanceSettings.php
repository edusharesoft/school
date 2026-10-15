<?php

namespace App\Services\Finance;

use App\Services\Settings\SettingsService;

/** Fee & accounting rules per branch (settings group "finance", institution / platform fallback). Currency: BDT. */
class FinanceSettings
{
    public const GROUP = 'finance';

    public const DEFAULTS = [
        'fy_start_month' => 7,            // financial year start (7 = July, Bangladesh fiscal year)
        'due_day' => 10,                  // monthly invoices fall due on this day of the month
        'auto_generate' => true,          // generate next month's invoices automatically (scheduler)
        'late_fee_mode' => 'fixed',       // none | fixed | percent
        'late_fee_amount' => 50,          // ৳ (fixed) or % of the invoice balance (percent)
        'late_fee_grace_days' => 5,       // days after the due date before the late fee is charged
        'sibling_discount_percent' => 0,  // automatic tuition discount for the 2nd, 3rd … child sharing a guardian
        'receipt_note' => '',             // printed on money receipts
        'payroll_deduct_unpaid' => true,  // deduct absent days and leave without pay from salary
        'payroll_days_basis' => 30,       // daily rate = basic ÷ this number of days
    ];

    public function __construct(protected SettingsService $settings) {}

    public function get(string $key): mixed
    {
        return $this->settings->get(self::GROUP, $key, self::DEFAULTS[$key] ?? null);
    }

    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function save(array $values): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $values)) {
                $this->settings->set(self::GROUP, $key, $values[$key], 'branch');
            }
        }
    }
}
