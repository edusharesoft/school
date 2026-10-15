<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Time-based one-time passwords (RFC 6238: HMAC-SHA1, 6 digits, 30 s) without any package. */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function code(string $secret, ?int $step = null): string
    {
        $step ??= intdiv(time(), 30);
        $hash = hash_hmac('sha1', pack('J', $step), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** @return int|null the matched 30-second step (so a code cannot be used twice), or null */
    public function verify(string $secret, string $code, ?int $notBeforeStep = null, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', (string) $code)) {
            return null;
        }
        $current = intdiv($now ?? time(), 30);
        $window = (int) config('security.two_factor.window', 1);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $current + $i;
            if ($notBeforeStep !== null && $step <= $notBeforeStep) {
                continue;
            }
            if (hash_equals($this->code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function uri(string $secret, string $account): string
    {
        $issuer = (string) config('security.two_factor.issuer', 'School');

        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    /** @return array{0: array<int,string>, 1: array<int,string>} [plain codes to show once, hashes to store] */
    public function recoveryCodes(): array
    {
        $plain = [];
        for ($i = 0; $i < (int) config('security.two_factor.recovery_codes', 8); $i++) {
            $plain[] = strtolower(substr(bin2hex(random_bytes(5)), 0, 5).'-'.substr(bin2hex(random_bytes(5)), 0, 5));
        }

        return [$plain, array_map(fn ($c) => hash('sha256', $c), $plain)];
    }

    /** Try a code the user typed: authenticator code first, then a recovery code (used up on success). */
    public function check(User $user, string $input): bool
    {
        $input = trim($input);
        if (preg_match('/^\d[\d\s]{5,}$/', $input)) {
            $step = $this->verify((string) $user->two_factor_secret, $input, $user->two_factor_last_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();

                return true;
            }

            return false;
        }

        $hash = hash('sha256', strtolower($input));
        $codes = (array) $user->two_factor_recovery_codes;
        foreach ($codes as $i => $stored) {
            if (hash_equals((string) $stored, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function passwordOk(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    protected function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    protected function base32Decode(string $b32): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32))) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
