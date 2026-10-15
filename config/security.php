<?php

/*
|--------------------------------------------------------------------------
| Security (Part 7)
|--------------------------------------------------------------------------
| Two-factor authentication (TOTP, RFC 6238 — Google Authenticator, Microsoft Authenticator, Authy …) is optional for
| everyone. List role names here (comma separated in .env) to make it mandatory: those users are sent to the
| Account security page until they have set it up.
*/

return [
    'two_factor' => [
        'required_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_2FA_REQUIRED_ROLES', ''))))),
        'issuer' => env('SECURITY_2FA_ISSUER', env('APP_NAME', 'School')),
        'window' => 1,                  // accept the previous and the next 30-second code (clock drift)
        'challenge_minutes' => 10,
        'recovery_codes' => 8,
    ],
];
