<?php

namespace App\Services\Library;

use App\Services\Settings\SettingsService;

/** Library rules per branch (settings group "library"; the defaults live in config/library.php). */
class LibrarySettings
{
    public const GROUP = 'library';

    public function __construct(protected SettingsService $settings) {}

    public function defaults(): array
    {
        return config('library.defaults');
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

    public function save(array $values): void
    {
        foreach ($this->defaults() as $key => $default) {
            if (array_key_exists($key, $values)) {
                $this->settings->set(self::GROUP, $key, is_bool($default) ? (bool) $values[$key] : (is_float($default) ? (float) $values[$key] : (int) $values[$key]), 'branch');
            }
        }
    }

    /** Borrowing limits for a member type. @return array{max_books:int, loan_days:int} */
    public function limits(string $memberType): array
    {
        $prefix = $memberType === 'employee' ? 'employee' : 'student';

        return ['max_books' => (int) $this->get($prefix.'_max_books'), 'loan_days' => (int) $this->get($prefix.'_loan_days')];
    }
}
