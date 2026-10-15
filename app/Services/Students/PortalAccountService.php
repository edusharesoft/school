<?php

namespace App\Services\Students;

use App\Enums\UserType;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student and parent portal logins. Students sign in with their student ID (username), guardians with
 * their mobile number; an e-mail is optional (a non-routable placeholder is stored when none is given,
 * so password resets for these accounts are done by the branch office).
 */
class PortalAccountService
{
    public function __construct(protected TenantContext $tenant) {}

    public function forStudent(Student $student, string $password, ?string $email, User $actor): User
    {
        if ($student->user_id) {
            throw ValidationException::withMessages(['password' => __('This student already has a portal login.')]);
        }
        $username = strtolower($student->student_code);
        if (User::withTrashed()->where('username', $username)->exists()) {
            throw ValidationException::withMessages(['password' => __('The username :username is already taken.', ['username' => $username])]);
        }

        return DB::transaction(function () use ($student, $password, $email, $actor, $username) {
            $user = $this->makeUser($student->name, $student->name_bn, $email ?: $username.'@student.portal.invalid', $username, null, UserType::Student, $password, 'student', $actor);
            $student->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    public function forGuardian(Guardian $guardian, string $password, ?string $email, User $actor): User
    {
        if ($guardian->user_id) {
            throw ValidationException::withMessages(['password' => __('This guardian already has a portal login.')]);
        }
        $phone = $guardian->phone;
        if (! $phone || User::withTrashed()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['password' => __('The mobile number :phone is already used by another login.', ['phone' => (string) $phone])]);
        }

        return DB::transaction(function () use ($guardian, $password, $email, $actor, $phone) {
            $placeholder = 'g'.$guardian->id.'.'.preg_replace('/\D/', '', $phone).'@parent.portal.invalid';
            $user = $this->makeUser($guardian->name, $guardian->name_bn, $email ?: $placeholder, null, $phone, UserType::Parent, $password, 'parent', $actor);
            $guardian->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password, 'password_changed_at' => null])->save();
        $user->tokens()->delete();
    }

    protected function makeUser(string $name, ?string $nameBn, string $email, ?string $username, ?string $phone, UserType $type, string $password, string $role, User $actor): User
    {
        if (User::withTrashed()->where('email', strtolower($email))->exists()) {
            throw ValidationException::withMessages(['email' => __('This e-mail address is already used by another login.')]);
        }
        $branch = $this->tenant->branch();

        $user = new User([
            'name' => $name, 'name_bn' => $nameBn, 'email' => strtolower($email), 'username' => $username, 'phone' => $phone,
            'user_type' => $type, 'status' => 'active', 'password' => $password,
        ]);
        $user->institution_id = $branch->institution_id;
        $user->default_branch_id = $branch->id;
        $user->save();

        $user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => true, 'granted_by' => $actor->id]]);
        $user->assignRole($role);

        return $user;
    }
}
