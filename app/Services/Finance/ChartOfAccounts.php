<?php

namespace App\Services\Finance;

use App\Models\Finance\Account;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Default chart of accounts of a branch, created on first use (idempotent). System accounts are found by
 * their system_key; the office may add, rename and deactivate accounts but system keys never change.
 */
class ChartOfAccounts
{
    /** [code, name, name_bn, type, is_group, system_key, kind, parent_code] */
    public const DEFAULT = [
        ['1000', 'Assets', 'সম্পদ', 'asset', true, 'assets', null, null],
        ['1100', 'Cash in hand', 'হাতে নগদ', 'asset', false, 'cash', 'cash', '1000'],
        ['1200', 'Bank account', 'ব্যাংক হিসাব', 'asset', false, 'bank', 'bank', '1000'],
        ['1300', 'Mobile banking (bKash / Nagad / Rocket)', 'মোবাইল ব্যাংকিং', 'asset', false, 'mobile', 'mobile', '1000'],
        ['1400', 'Student fees receivable', 'শিক্ষার্থী ফি প্রাপ্য', 'asset', false, 'fees_receivable', null, '1000'],
        ['1500', 'Advances & prepayments', 'অগ্রিম', 'asset', false, 'advances', null, '1000'],
        ['1600', 'Inventory stock', 'মজুদ সম্পদ', 'asset', false, 'inventory_stock', null, '1000'],
        ['1700', 'Fixed assets (at cost)', 'স্থায়ী সম্পদ (ক্রয়মূল্যে)', 'asset', false, 'fixed_assets', null, '1000'],
        ['1790', 'Accumulated depreciation', 'সঞ্চিত অবচয়', 'asset', false, 'accumulated_depreciation', null, '1000'],
        ['2000', 'Liabilities', 'দায়', 'liability', true, 'liabilities', null, null],
        ['2100', 'Student advances (fees received in advance)', 'শিক্ষার্থী অগ্রিম', 'liability', false, 'student_advance', null, '2000'],
        ['2200', 'Salaries payable', 'প্রদেয় বেতন', 'liability', false, 'salary_payable', null, '2000'],
        ['2300', 'Accounts payable (suppliers)', 'প্রদেয় হিসাব (সরবরাহকারী)', 'liability', false, 'payable', null, '2000'],
        ['2400', 'Salary deductions payable (PF, tax …)', 'বেতন কর্তন প্রদেয়', 'liability', false, 'salary_deductions', null, '2000'],
        ['3000', 'Fund & equity', 'তহবিল', 'equity', true, 'equity', null, null],
        ['3100', 'Institution fund', 'প্রতিষ্ঠান তহবিল', 'equity', false, 'fund', null, '3000'],
        ['3200', 'Opening balance equity', 'প্রারম্ভিক জের', 'equity', false, 'opening_balance', null, '3000'],
        ['4000', 'Income', 'আয়', 'income', true, 'income', null, null],
        ['4100', 'Fee income', 'ফি আয়', 'income', true, 'fee_income_group', null, '4000'],
        ['4190', 'Other fee income', 'অন্যান্য ফি আয়', 'income', false, 'fee_income', null, '4100'],
        ['4800', 'Late fees & fines', 'বিলম্ব ফি ও জরিমানা', 'income', false, 'late_fee', null, '4000'],
        ['4900', 'Other income', 'অন্যান্য আয়', 'income', false, 'other_income', null, '4000'],
        ['4950', 'Gain on disposal of assets', 'সম্পদ নিষ্পত্তিতে লাভ', 'income', false, 'asset_disposal_gain', null, '4000'],
        ['5000', 'Expenses', 'ব্যয়', 'expense', true, 'expenses', null, null],
        ['5100', 'Fee discounts, scholarships & waivers', 'ফি ছাড় ও বৃত্তি', 'expense', false, 'fee_discount', null, '5000'],
        ['5200', 'Salaries & allowances', 'বেতন ও ভাতা', 'expense', false, 'salaries', null, '5000'],
        ['5300', 'Utilities (electricity, water, gas, internet)', 'ইউটিলিটি', 'expense', false, 'utilities', null, '5000'],
        ['5400', 'Stationery & printing', 'স্টেশনারি ও মুদ্রণ', 'expense', false, 'stationery', null, '5000'],
        ['5450', 'Consumables & supplies used', 'ব্যবহৃত মালামাল', 'expense', false, 'stock_consumed', null, '5000'],
        ['5500', 'Repairs & maintenance', 'মেরামত ও রক্ষণাবেক্ষণ', 'expense', false, 'maintenance', null, '5000'],
        ['5600', 'Rent', 'ভাড়া', 'expense', false, 'rent', null, '5000'],
        ['5650', 'Depreciation', 'অবচয়', 'expense', false, 'depreciation', null, '5000'],
        ['5700', 'Transport & travel', 'যাতায়াত', 'expense', false, 'travel', null, '5000'],
        ['5800', 'Events & programmes', 'অনুষ্ঠান', 'expense', false, 'events', null, '5000'],
        ['5950', 'Stock adjustments (shortage / surplus)', 'মজুদ সমন্বয় (ঘাটতি / উদ্বৃত্ত)', 'expense', false, 'stock_adjustment', null, '5000'],
        ['5900', 'Miscellaneous expenses', 'বিবিধ ব্যয়', 'expense', false, 'misc_expense', null, '5000'],
        ['5960', 'Loss on disposal of assets', 'সম্পদ নিষ্পত্তিতে ক্ষতি', 'expense', false, 'asset_disposal_loss', null, '5000'],
    ];

    /** Create any missing default account for the current branch. */
    public function ensure(): void
    {
        // Every default system account must exist — also in branches whose chart was created before a new one was added.
        $keys = array_column(self::DEFAULT, 5);
        if (Account::query()->whereIn('system_key', $keys)->count() >= count(array_unique($keys))) {
            return;
        }

        DB::transaction(function () {
            $existing = Account::query()->get()->keyBy('system_key');
            $byCode = Account::query()->pluck('id', 'code');
            foreach (self::DEFAULT as [$code, $name, $nameBn, $type, $group, $key, $kind, $parent]) {
                if ($existing->has($key)) {
                    continue;
                }
                if ($byCode->has($code)) {
                    // The office already used this code for its own account: take the next free code in the same block.
                    $free = (int) $code;
                    while ($byCode->has((string) $free)) {
                        $free++;
                    }
                    $code = (string) $free;
                }
                $account = new Account([
                    'code' => $code, 'name' => $name, 'name_bn' => $nameBn, 'type' => $type, 'is_group' => $group, 'kind' => $kind,
                    'parent_id' => $parent ? $byCode[$parent] ?? null : null, 'is_active' => true,
                ]);
                $account->forceFill(['system_key' => $key])->save();
                $byCode[$code] = $account->id;
            }
        });
    }

    public function get(string $systemKey): Account
    {
        $account = Account::query()->where('system_key', $systemKey)->first();
        if (! $account) {
            $this->ensure();
            $account = Account::query()->where('system_key', $systemKey)->first();
        }

        return $account ?? throw new RuntimeException("System account [$systemKey] is missing.");
    }

    public function id(string $systemKey): int
    {
        return $this->get($systemKey)->id;
    }

    /** Default receiving account for a payment method. */
    public function forMethod(string $method): Account
    {
        return $this->get(match ($method) {
            'cash' => 'cash',
            'bkash', 'nagad', 'rocket' => 'mobile',
            default => 'bank',
        });
    }

    /** Income account for a new fee head: a sub-account under "Fee income" (4101, 4102 …). */
    public function incomeAccountForHead(string $name, ?string $nameBn = null): Account
    {
        $group = $this->get('fee_income_group');
        $codes = Account::query()->where('code', 'like', '41%')->pluck('code')->map(fn ($c) => (int) $c)->filter(fn ($c) => $c > 4100 && $c < 4190);
        $code = (string) (($codes->max() ?: 4100) + 1);

        return Account::query()->create([
            'parent_id' => $group->id, 'code' => $code, 'name' => __('Fee income').' — '.$name, 'name_bn' => $nameBn ? 'ফি আয় — '.$nameBn : null,
            'type' => 'income', 'is_group' => false, 'is_active' => true,
        ]);
    }
}
