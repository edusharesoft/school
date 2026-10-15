<?php

namespace App\Services\Settings;

use App\Support\AuthTheme;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Reads / writes the "Logo & login page" settings (AuthTheme) at one level: platform, institution or branch.
 * Images are stored on the public disk under branding/{level-key}/.
 */
class BrandingSettings
{
    public const IMAGE_KEYS = ['auth_login_bg', 'auth_register_bg'];

    public const TEXT_KEYS = ['site_name', 'footer_text', 'auth_login_video', 'auth_register_video'];

    public function __construct(protected SettingsService $settings) {}

    /** Current values stored at exactly this level (inherited values are not shown as own values). */
    public function values(string $level): array
    {
        $out = [];
        foreach ([...self::TEXT_KEYS, ...self::IMAGE_KEYS, 'site_logo', 'auth_show_video', 'auth_dim', 'allow_registration'] as $key) {
            $out[$key] = $this->settings->at($level, AuthTheme::GROUP, $key);
        }

        return $out;
    }

    public static function rules(bool $platform): array
    {
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        return array_filter([
            'site_name' => ['nullable', 'string', 'max:120'],
            'footer_text' => ['nullable', 'string', 'max:160'],
            'auth_login_video' => ['nullable', 'string', 'max:255', function ($attr, $value, $fail) {
                if ($value && ! AuthTheme::youtubeId($value)) {
                    $fail(__('Enter a valid YouTube link or video id.'));
                }
            }],
            'auth_register_video' => ['nullable', 'string', 'max:255', function ($attr, $value, $fail) {
                if ($value && ! AuthTheme::youtubeId($value)) {
                    $fail(__('Enter a valid YouTube link or video id.'));
                }
            }],
            'auth_show_video' => ['nullable', 'boolean'],
            'auth_dim' => ['nullable', 'numeric', 'min:0', 'max:0.85'],
            'site_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'auth_login_bg' => $image,
            'auth_register_bg' => $image,
            'allow_registration' => $platform ? ['nullable', 'boolean'] : null,
        ]);
    }

    public function save(Request $request, string $level, string $folder, bool $platform): void
    {
        foreach (self::TEXT_KEYS as $key) {
            $value = trim((string) $request->input($key, ''));
            $value === '' ? $this->forget($key, $level) : $this->settings->set(AuthTheme::GROUP, $key, $value, $level);
        }

        $this->settings->set(AuthTheme::GROUP, 'auth_show_video', $request->boolean('auth_show_video'), $level);
        if ($request->filled('auth_dim')) {
            $this->settings->set(AuthTheme::GROUP, 'auth_dim', (float) $request->input('auth_dim'), $level);
        }
        if ($platform) {
            $this->settings->set(AuthTheme::GROUP, 'allow_registration', $request->boolean('allow_registration'), $level);
        }

        foreach ([...self::IMAGE_KEYS, 'site_logo'] as $key) {
            $this->image($request, $key, $level, $folder);
        }
    }

    protected function image(Request $request, string $key, string $level, string $folder): void
    {
        $old = $this->settings->at($level, AuthTheme::GROUP, $key);

        if ($request->boolean('remove_'.$key) && $old) {
            Storage::disk('public')->delete($old);
            $this->forget($key, $level);
            $old = null;
        }

        $file = $request->file($key);
        if ($file instanceof UploadedFile) {
            $path = $file->store('branding/'.$folder, 'public');
            $this->settings->set(AuthTheme::GROUP, $key, $path, $level);
            if ($old && $old !== $path) {
                Storage::disk('public')->delete($old);
            }
        }
    }

    protected function forget(string $key, string $level): void
    {
        $this->settings->forget(AuthTheme::GROUP, $key, $level);
    }
}
