<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Small view helpers for the public website. */
class Website
{
    /**
     * Resolve an image setting to a URL. Absolute URLs pass through; otherwise the path is looked up on the
     * public disk (uploads) and then under /public (bundled defaults). Returns null when the file does not
     * exist, so a missing image never renders as a broken picture.
     */
    public static function img(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return is_file(public_path($path)) ? asset($path) : null;
    }

    /** Menu / footer label of a CMS page or any model exposing displayTitle(). */
    public static function label(object $item): string
    {
        return method_exists($item, 'displayTitle') ? $item->displayTitle() : (string) ($item->title ?? '');
    }
}
