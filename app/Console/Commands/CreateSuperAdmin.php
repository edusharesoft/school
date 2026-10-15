<?php

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/** Creates (or resets) the platform Super Admin account — use once on a fresh production install. */
class CreateSuperAdmin extends Command
{
    protected $signature = 'school:create-super-admin {--email=} {--name=Super Admin} {--password=}';

    protected $description = 'Create or reset the platform Super Admin account';

    public function handle(): int
    {
        if (! Role::query()->where('name', 'super-admin')->exists()) {
            $this->error('Roles are not seeded yet. Run: php artisan db:seed --class=RolesAndPermissionsSeeder');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: $this->ask('E-mail');
        $password = $this->option('password') ?: $this->secret('Password (min 10, letters+numbers+symbol)');

        $validator = Validator::make(compact('email', 'password'), [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(10)->letters()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::withTrashed()->firstOrNew(['email' => strtolower($email)]);
        $user->fill([
            'name' => $this->option('name'),
            'user_type' => UserType::Platform,
            'status' => 'active',
            'password' => $password,
        ]);
        $user->institution_id = null;
        $user->deleted_at = null;
        $user->email_verified_at ??= now();
        $user->password_changed_at = now();
        $user->save();
        $user->syncRoles(['super-admin']);

        $this->info("Super Admin ready: {$user->email}");

        return self::SUCCESS;
    }
}
