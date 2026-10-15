<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Storage;

/**
 * Look of the branch's printed documents (settings group "documents"): who signs, signature and seal images,
 * accent colour, certificate style and the ID card options. Falls back to the branch's principal / brand colour.
 */
class DocumentSettings
{
    public const GROUP = 'documents';

    public const CERT_STYLES = ['classic', 'modern', 'plain'];

    public const CARD_THEMES = ['brand', 'blue', 'green', 'maroon', 'slate'];

    public const THEME_COLORS = ['blue' => '#1d4ed8', 'green' => '#15803d', 'maroon' => '#9f1239', 'slate' => '#334155'];

    public function __construct(protected SettingsService $settings) {}

    public function defaults(): array
    {
        return [
            'signatory_name' => '',
            'signatory_title' => 'Principal / Head Teacher',
            'signature_path' => '',
            'seal_path' => '',
            'accent_color' => '',
            'certificate_style' => 'classic',
            'certificate_footer' => '',
            'card_theme' => 'brand',
            'card_validity_months' => 12,
            'card_show_blood_group' => true,
            'card_show_guardian_phone' => true,
            'card_emergency_phone' => '',
            'card_back_text' => "This card is the property of the institution.\nIf found, please return it to the address given.",
        ];
    }

    public function get(string $key): mixed
    {
        return $this->settings->get(self::GROUP, $key, $this->defaults()[$key] ?? null);
    }

    public function all(): array
    {
        $out = [];
        foreach (array_keys($this->defaults()) as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    /** Values the form may save (images are handled by the controller). */
    public function save(array $values): void
    {
        foreach ($this->defaults() as $key => $default) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            $value = is_bool($default) ? (bool) $value : (is_int($default) ? (int) $value : trim((string) $value));
            $this->settings->set(self::GROUP, $key, $value, 'branch');
        }
    }

    public function signatoryName(?Branch $branch = null): string
    {
        $branch ??= tenant()->branch();

        return (string) ($this->get('signatory_name') ?: $branch?->principal_name ?: __('Principal'));
    }

    public function signatoryTitle(): string
    {
        return (string) ($this->get('signatory_title') ?: 'Principal / Head Teacher');
    }

    public function accent(?Branch $branch = null): string
    {
        $branch ??= tenant()->branch();
        $color = (string) $this->get('accent_color');

        return $this->validColor($color) ? $color : ($branch?->brandColor() ?? '#1f6f5c');
    }

    /** Colour of the ID card header band. */
    public function cardColor(?Branch $branch = null): string
    {
        $theme = (string) $this->get('card_theme');

        return self::THEME_COLORS[$theme] ?? $this->accent($branch);
    }

    /** Absolute file path of a stored image setting on the public disk, or null. */
    public function imagePath(string $key): ?string
    {
        $path = (string) $this->get($key);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    public function setImage(string $key, ?string $path): void
    {
        $this->settings->set(self::GROUP, $key, (string) $path, 'branch');
    }

    public function validColor(?string $color): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color);
    }
}
