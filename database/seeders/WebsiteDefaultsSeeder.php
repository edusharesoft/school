<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Website\Page;
use Illuminate\Database\Seeder;

/**
 * Creates the pages the master home-page design links to (About Us, Academic, Regulations, Contact,
 * FAQ, Testimonials, Privacy Policy, Terms of Service, and the "Our Campus" footer pages) for every
 * institution. Idempotent: existing pages (even edited ones) are never touched. The starter text is
 * deliberately short and neutral — administrators are expected to replace it from Website → Pages.
 */
class WebsiteDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Institution::query()->get() as $institution) {
            foreach (config('website.default_pages', []) as $def) {
                if (Page::query()->withTrashed()->where('institution_id', $institution->id)->where('slug', $def['slug'])->exists()) {
                    continue;
                }

                $page = new Page($def + [
                    'body' => "## {$def['title']}\n\nThis page of **{$institution->name}** is being prepared. Please check back soon.",
                    'body_bn' => "## {$def['title_bn']}\n\n**{$institution->name_bn}** এর এই পাতাটি প্রস্তুত করা হচ্ছে। অনুগ্রহ করে কিছুদিন পর আবার দেখুন।",
                    'status' => 'published',
                ]);
                $page->institution_id = $institution->id;
                $page->save();
            }
        }
    }
}
