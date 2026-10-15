<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinanceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Branch fee rules: due day, late fee, sibling discount, financial year, automatic generation. */
class FinanceSettingsController extends Controller
{
    public function __construct(protected FinanceSettings $settings) {}

    public function edit(): View
    {
        $this->authorize('fees.view');

        return view('branch.finance.settings', ['values' => $this->settings->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('fees.update');
        $data = $request->validate([
            'fy_start_month' => ['required', 'integer', 'between:1,12'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'auto_generate' => ['nullable', 'boolean'],
            'late_fee_mode' => ['required', Rule::in(['none', 'fixed', 'percent'])],
            'late_fee_amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'late_fee_grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'sibling_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'receipt_note' => ['nullable', 'string', 'max:255'],
            'payroll_days_basis' => ['nullable', 'integer', 'between:26,31'],
            'payroll_deduct_unpaid' => ['nullable', 'boolean'],
        ]);
        if ($data['late_fee_mode'] === 'percent' && (float) $data['late_fee_amount'] > 100) {
            return back()->withErrors(['late_fee_amount' => __('A percentage cannot exceed 100.')])->withInput();
        }

        $this->settings->save([
            'fy_start_month' => (int) $data['fy_start_month'], 'due_day' => (int) $data['due_day'], 'auto_generate' => $request->boolean('auto_generate'),
            'late_fee_mode' => $data['late_fee_mode'], 'late_fee_amount' => (float) $data['late_fee_amount'], 'late_fee_grace_days' => (int) $data['late_fee_grace_days'],
            'sibling_discount_percent' => (float) $data['sibling_discount_percent'], 'receipt_note' => (string) ($data['receipt_note'] ?? ''),
        ]);
        if (isset($data['payroll_days_basis'])) { // payroll part of the form
            $this->settings->save(['payroll_days_basis' => (int) $data['payroll_days_basis'], 'payroll_deduct_unpaid' => $request->boolean('payroll_deduct_unpaid')]);
        }

        return back()->with('success', __('Fee settings saved.'));
    }
}
