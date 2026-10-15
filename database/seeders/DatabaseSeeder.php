<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production: roles/permissions + platform Super Admin only.
     * Local/staging: also a demo institution with SEED_DEMO_BRANCHES branches and sample users.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([DemoInstitutionSeeder::class, DemoAcademicSeeder::class, DemoHrAttendanceSeeder::class, DemoAdmissionSeeder::class, DemoTimetableExamSeeder::class, DemoResultsSeeder::class, DemoFinanceSeeder::class, DemoAccountingSeeder::class, DemoInventorySeeder::class, DemoAssetsSeeder::class, DemoCommunicationSeeder::class, WebsiteDefaultsSeeder::class]);
        }
    }
}
