<?php

namespace App\Http\Controllers\Branch\Library;

use App\Http\Controllers\Controller;
use App\Services\Library\LibrarySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Borrowing limits, loan period, fines and reservation rules. permission: library.update */
class SettingsController extends Controller
{
    public function __construct(protected LibrarySettings $settings) {}

    public function edit(): View
    {
        $this->authorize('library.update');

        return view('branch.library.settings', ['values' => $this->settings->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('library.update');
        $data = $request->validate([
            'student_max_books' => ['required', 'integer', 'min:1', 'max:50'], 'student_loan_days' => ['required', 'integer', 'min:1', 'max:365'],
            'employee_max_books' => ['required', 'integer', 'min:1', 'max:50'], 'employee_loan_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_renewals' => ['required', 'integer', 'min:0', 'max:10'], 'fine_per_day' => ['required', 'numeric', 'min:0', 'max:10000'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'], 'max_fine_per_loan' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'lost_fee_percent' => ['required', 'integer', 'min:0', 'max:500'], 'reservation_hold_days' => ['required', 'integer', 'min:1', 'max:30'],
            'overdue_reminder_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);
        $data['block_on_unpaid_fines'] = $request->boolean('block_on_unpaid_fines');
        $data['block_when_overdue'] = $request->boolean('block_when_overdue');
        $this->settings->save($data);

        return back()->with('success', __('Library rules saved.'));
    }
}
