<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Communication\Notice;
use App\Models\Institution;
use App\Models\User;
use App\Services\Communication\NoticeService;
use Illuminate\Database\Seeder;

/**
 * Demo notice board for every demo branch (local/staging only): a pinned exam notice for students and guardians,
 * a holiday notice for everyone, a staff meeting notice and one scheduled for tomorrow. Publishing distributes
 * in-app notifications, so the demo student / parent logins see the bell badge. SMS, e-mail and WhatsApp stay
 * off until a branch configures a provider. Idempotent and resumable: existing titles are not repeated, and a published
 * notice that was never distributed (an interrupted run) is distributed now.
 */
class DemoCommunicationSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            $this->command?->warn('Demo institution not found — run DemoInstitutionSeeder first.');

            return;
        }

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($branch) {
                $admin = User::query()->where('email', "admin.branch{$branch->sort_order}@demo.test")->first();
                if (! $admin) {
                    return;
                }
                $service = app(NoticeService::class);
                $rows = [
                    ['title' => 'অর্ধবার্ষিক পরীক্ষার রুটিন প্রকাশ', 'category' => 'exam', 'audience' => 'students', 'is_pinned' => true,
                        'body' => "আগামী মাসের প্রথম সপ্তাহ থেকে অর্ধবার্ষিক পরীক্ষা শুরু হবে। বিস্তারিত রুটিন শ্রেণি শিক্ষকের কাছ থেকে সংগ্রহ করো।\nপরীক্ষার আগে প্রবেশপত্র পোর্টাল থেকে ডাউনলোড করতে হবে।"],
                    ['title' => 'Guardians meeting on Saturday', 'category' => 'meeting', 'audience' => 'guardians',
                        'body' => "All guardians are invited to the guardians' meeting on Saturday at 10:00 in the school hall.\nWe will discuss the half-yearly results and the new co-curricular programme."],
                    ['title' => 'মহান স্বাধীনতা দিবস উপলক্ষে ছুটি', 'category' => 'holiday', 'audience' => 'all', 'expires_at' => now()->addDays(20),
                        'body' => '২৬ মার্চ মহান স্বাধীনতা ও জাতীয় দিবস উপলক্ষে প্রতিষ্ঠান বন্ধ থাকবে। সকাল ৮টায় জাতীয় পতাকা উত্তোলন অনুষ্ঠানে সবাইকে উপস্থিত থাকার অনুরোধ করা হলো।'],
                    ['title' => 'Staff meeting — lesson plans', 'category' => 'academic', 'audience' => 'staff',
                        'body' => 'All teachers please submit next month\'s lesson plans to the academic coordinator by Thursday.'],
                    ['title' => 'Science fair registration opens', 'category' => 'event', 'audience' => 'all', 'publish_at' => now()->addDay()->setTime(8, 0),
                        'body' => 'Registration for the annual science fair opens tomorrow. Teams of up to three students may register with their class teacher.'],
                ];
                foreach ($rows as $row) {
                    $notice = Notice::query()->where('title', $row['title'])->first();
                    if (! $notice) {
                        $notice = $service->save($row + ['is_emergency' => false, 'is_public' => false, 'is_pinned' => $row['is_pinned'] ?? false], null, $admin);
                    }
                    if ($notice->status === 'draft') {
                        $service->publish($notice, $admin);
                    } elseif ($notice->status === 'published' && ! $notice->distributed_at) {
                        $service->distribute($notice, $admin);
                    }
                }
            });
        }
    }
}
