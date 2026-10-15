<?php

namespace App\Http\Requests\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:191'],   // e-mail, phone or username
            'password' => ['required', 'string', 'max:191'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** Validates credentials AND tenant access. Rate limiting is applied by the 'throttle:login' route middleware. */
    public function authenticate(): User
    {
        $login = trim((string) $this->input('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : (preg_match('/^\+?\d{6,15}$/', $login) ? 'phone' : 'username');
        $value = $field === 'email' ? strtolower($login) : $login;

        /** @var User|null $user */
        $user = User::query()->where($field, $value)->first();

        if (! $user || ! Hash::check((string) $this->input('password'), $user->password)) {
            event(new Failed('web', $user, [$field => $value]));

            throw ValidationException::withMessages(['login' => __('These credentials do not match our records.')]);
        }

        if (! LoginController::userMayEnterTenant($user, app(TenantContext::class))) {
            event(new Failed('web', $user, [$field => $value]));

            // Deliberately generic: do not reveal which branches an account belongs to.
            throw ValidationException::withMessages(['login' => __('This account cannot sign in here.')]);
        }

        return $user;
    }
}
