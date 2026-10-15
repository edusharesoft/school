<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Institution;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Storage;

/**
 * Look & feel of the login / registration pages and panel branding (same model as the
 * Multi-Branch eCommerce platform). Values come from Settings → "Logo & login page",
 * resolved branch → institution → platform by SettingsService.
 */
class AuthTheme
{
    public const GROUP = 'site';

    public const DEFAULT_FOOTER = 'Developed By : Shataj Soft';

    protected static function setting(string $key, mixed $default = null): mixed
    {
        $value = app(SettingsService::class)->get(self::GROUP, $key, $default);

        return $value === '' ? $default : $value;
    }

    /** Branch logo → institution logo → platform logo → null (the view then shows an icon). */
    public static function logo(?Branch $branch = null, ?Institution $institution = null): ?string
    {
        $institution ??= $branch?->institution;

        foreach ([$branch?->logo_path, $institution?->logo_path, static::setting('site_logo')] as $path) {
            if ($path) {
                return Storage::disk('public')->url($path);
            }
        }

        return null;
    }

    public static function name(?Branch $branch = null): string
    {
        return $branch?->displayName() ?? (string) static::setting('site_name', config('app.name'));
    }

    /** @param 'login'|'register' $kind */
    public static function background(string $kind = 'login'): ?string
    {
        $path = static::setting("auth_{$kind}_bg") ?: static::setting('auth_login_bg');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** YouTube video id for the page, or null when videos are switched off / not set. */
    public static function video(string $kind = 'login'): ?string
    {
        if (! filter_var(static::setting('auth_show_video', true), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        return static::youtubeId((string) (static::setting("auth_{$kind}_video") ?: static::setting('auth_login_video')));
    }

    /** Overlay darkness 0–0.85 over the background photo. */
    public static function dim(): float
    {
        return max(0.0, min(0.85, (float) static::setting('auth_dim', 0.45)));
    }

    public static function registrationOpen(): bool
    {
        return filter_var(app(SettingsService::class)->platform(self::GROUP, 'allow_registration', false), FILTER_VALIDATE_BOOL);
    }

    public static function youtubeId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }
        if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function footer(): string
    {
        return (string) static::setting('footer_text', self::DEFAULT_FOOTER);
    }

    /** "Developed By : Shataj Soft" → ['Developed By', 'Shataj Soft'] (the name is shown in bold). */
    public static function footerParts(): array
    {
        $footer = static::footer();

        if (str_contains($footer, ':')) {
            return array_map('trim', explode(':', $footer, 2));
        }

        return preg_match('/^(.*\bby)\s+(.+)$/i', $footer, $m) ? [$m[1], $m[2]] : ['', $footer];
    }
}
