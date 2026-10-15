<?php

namespace App\Services\Website;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * The banner slides of a hero picture (central website and branch websites).
 *
 * A slide is {image, title, text, link}. `hero.slides` holds the list; the old single `hero.image` is still
 * honoured (it is shown as one slide until the administrator saves slides) and always mirrors the first slide,
 * so a branch without slides of its own can fall back to the institution's.
 */
class HeroSlides
{
    public const MAX = 8;

    /**
     * The slides of a hero block, legacy single picture included.
     *
     * @param  array<string,mixed>  $hero
     * @return list<array{image:string,title:?string,text:?string,link:?string}>
     */
    public static function effective(array $hero): array
    {
        $slides = [];
        foreach ((array) ($hero['slides'] ?? []) as $slide) {
            if (is_array($slide) && ! empty($slide['image'])) {
                $slides[] = self::clean($slide, (string) $slide['image']);
            }
        }
        if ($slides === [] && ! empty($hero['image']) && is_string($hero['image'])) {
            $slides[] = self::clean([], $hero['image']);
        }

        return $slides;
    }

    /**
     * Rebuild the slide list from the settings form.
     *
     * @param  list<array<string,mixed>>  $old  slides before the save (see effective())
     * @param  array<int|string,array<string,mixed>>|null  $rows  submitted rows: path, title, text, link, remove, position; null = keep as is
     * @param  array<int,UploadedFile>  $uploads  pictures to add at the end
     * @param  string  $dir  public-disk folder for new pictures
     * @param  Closure(string):void  $forget  deletes a stored picture (only the ones the caller owns)
     * @return list<array{image:string,title:?string,text:?string,link:?string}>
     */
    public function build(array $old, ?array $rows, array $uploads, string $dir, Closure $forget): array
    {
        $kept = $old;

        if ($rows !== null) {
            $known = array_column($old, null, 'image');   // a path from the browser is trusted only if it is already a slide
            $ordered = [];
            foreach ($rows as $key => $row) {
                $path = (string) ($row['path'] ?? '');
                if (! isset($known[$path]) || ! empty($row['remove'])) {
                    continue;
                }
                $ordered[] = [
                    'position' => is_numeric($row['position'] ?? null) ? (int) $row['position'] : PHP_INT_MAX,
                    'index' => count($ordered),
                    'slide' => self::clean($row, $path),
                ];
            }
            usort($ordered, fn ($a, $b) => [$a['position'], $a['index']] <=> [$b['position'], $b['index']]);
            $kept = array_map(fn ($o) => $o['slide'], $ordered);

            $keptPaths = array_column($kept, 'image');
            foreach ($old as $slide) {
                if (! in_array($slide['image'], $keptPaths, true)) {
                    $forget($slide['image']);
                }
            }
        }

        $kept = array_slice($kept, 0, self::MAX);
        foreach ($uploads as $file) {
            if (count($kept) >= self::MAX) {
                break;
            }
            if ($file instanceof UploadedFile) {
                $kept[] = self::clean([], $file->store($dir, 'public'));
            }
        }

        return array_values($kept);
    }

    /**
     * Write the slide list into a hero block (slides + the mirrored first picture).
     *
     * @param  array<string,mixed>  $hero
     * @param  list<array<string,mixed>>  $slides
     * @return array<string,mixed>
     */
    public static function store(array $hero, array $slides): array
    {
        $hero['slides'] = $slides;
        $hero['image'] = $slides[0]['image'] ?? null;

        return $hero;
    }

    /** @param  array<string,mixed>  $row */
    protected static function clean(array $row, string $image): array
    {
        $text = fn ($v, int $max) => ($v = trim((string) ($v ?? ''))) === '' ? null : mb_substr($v, 0, $max);
        $link = $text($row['link'] ?? null, 255);

        return [
            'image' => $image,
            'title' => $text($row['title'] ?? null, 120),
            'text' => $text($row['text'] ?? null, 200),
            'link' => $link !== null && preg_match('#^(https?://|/(?!/))#i', $link) ? $link : null,
        ];
    }
}
