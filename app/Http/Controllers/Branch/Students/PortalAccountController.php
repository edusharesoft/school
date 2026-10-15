<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Students\PortalAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Student & parent portal logins created from the student profile. */
class PortalAccountController extends Controller
{
    public function __construct(protected PortalAccountService $accounts) {}

    public function storeStudent(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);
        $data = $this->validated($request);
        $user = $this->accounts->forStudent($student, $data['password'], $data['email'] ?? null, $request->user());

        return back()->with('success', __('Student portal login created. Username: :username', ['username' => $user->username]));
    }

    public function storeGuardian(Request $request, Student $student, Guardian $guardian): RedirectResponse
    {
        $this->authorize('update', $student);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);
        $data = $this->validated($request);
        $user = $this->accounts->forGuardian($guardian, $data['password'], $data['email'] ?? null, $request->user());

        return back()->with('success', __('Parent portal login created. The guardian signs in with the mobile number :phone.', ['phone' => $user->phone]));
    }

    public function resetPassword(Request $request, Student $student, User $account): RedirectResponse
    {
        $this->authorize('update', $student);
        $linked = $account->id === $student->user_id || $student->guardians()->where('guardians.user_id', $account->id)->exists();
        abort_unless($linked && in_array($account->user_type?->value ?? $account->user_type, ['student', 'parent'], true), 404);
        $data = $request->validate(['password' => ['required', Password::min(8)->letters()->numbers()]]);

        $this->accounts->resetPassword($account, $data['password']);

        return back()->with('success', __('Password changed for :name.', ['name' => $account->name]));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'email' => ['nullable', 'email:rfc', 'max:191'],
        ]);
    }
}
