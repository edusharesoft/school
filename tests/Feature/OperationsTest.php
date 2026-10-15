<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Saas\BackupRun;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Monitoring\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Part 7 — backups with restore drill, system health, monitoring and the performance audit. */
class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['backup.include_files' => false, 'backup.passphrase' => null]);
    }

    protected function owner(): User
    {
        $u = User::factory()->create(['institution_id' => null, 'user_type' => 'platform']);
        $u->assignRole('super-admin');

        return $u;
    }

    public function test_backup_writes_a_verified_zip_with_row_counts(): void
    {
        $branch = $this->makeBranch();
        $this->branchUser($branch, 'branch-admin');
        $svc = app(BackupService::class);

        $run = $svc->run();
        $this->assertSame('done', $run->status, (string) $run->error);
        $this->assertGreaterThan(0, $run->bytes);
        $this->assertSame(DB::table('users')->count(), $this->manifest($run)['tables']['users']);
        $this->assertArrayNotHasKey('sessions', $this->manifest($run)['tables']);
        $this->assertSame($run->rows_count, array_sum($this->manifest($run)['tables']));

        $check = $svc->verify($run->fresh());
        $this->assertTrue($check['ok'], implode(' ', $check['notes']));
        $this->assertSame('ok', $run->fresh()->verify_status);
        $this->assertFalse($run->fresh()->drilled);
    }

    protected function manifest(BackupRun $run): array
    {
        $zip = new \ZipArchive;
        $zip->open(app(BackupService::class)->path($run));
        $m = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();

        return $m;
    }

    public function test_tampered_or_missing_backups_fail_the_check(): void
    {
        $this->makeBranch();
        $svc = app(BackupService::class);
        $run = $svc->run();
        $path = $svc->path($run);

        file_put_contents($path, 'x', FILE_APPEND);                                    // damaged after it was written
        $this->assertFalse($svc->verify($run->fresh())['ok']);
        $this->assertSame('failed', $run->fresh()->verify_status);

        unlink($path);
        $r = $svc->verify($run->fresh());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('missing', $r['notes'][0]);
    }

    public function test_restore_drill_loads_the_backup_into_a_scratch_database_and_compares_counts(): void
    {
        $branch = $this->makeBranch();
        $this->branchUser($branch, 'branch-admin');
        $this->inBranch($branch, fn () => Department::query()->create(['name' => 'Science', 'code' => 'SCI', 'is_active' => true]));
        $svc = app(BackupService::class);
        $run = $svc->run();

        $r = $svc->verify($run, true);
        $this->assertTrue($r['ok'], implode("\n", $r['notes']));
        $this->assertTrue($run->fresh()->drilled);
        $this->assertStringContainsString('Restore drill OK', $run->fresh()->verify_note);
    }

    public function test_restore_brings_back_deleted_data_and_files_and_encrypted_backups_need_the_passphrase(): void
    {
        $branch = $this->makeBranch();
        $this->inBranch($branch, fn () => Department::query()->create(['name' => 'Science', 'code' => 'SCI', 'is_active' => true]));
        $dir = storage_path('app/public/_bk_test');
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.'/a.txt', 'hello');
        config(['backup.include_files' => true, 'backup.passphrase' => 'correct horse']);

        try {
            $svc = app(BackupService::class);
            $run = $svc->run();
            $this->assertSame('done', $run->status, (string) $run->error);
            $this->assertTrue($run->encrypted);
            $this->assertGreaterThanOrEqual(1, $run->files_count);
            $this->assertTrue($svc->verify($run->fresh())['ok']);

            // Damage the live data and files, then restore.
            DB::statement('PRAGMA defer_foreign_keys = ON');                          // the test runs inside one transaction
            Department::withoutGlobalScopes()->delete();
            $this->inBranch($branch, fn () => Department::query()->create(['name' => 'Stray', 'code' => 'STR', 'is_active' => true]));
            unlink($dir.'/a.txt');
            $result = $svc->restore($run->fresh());
            $this->assertGreaterThan(0, $result['rows']);
            $names = Department::withoutGlobalScopes()->pluck('name')->all();
            $this->assertSame(['Science'], $names);
            $this->assertSame('hello', file_get_contents($dir.'/a.txt'));

            // Wrong passphrase: the backup cannot be read.
            config(['backup.passphrase' => 'wrong']);
            $this->assertFalse($svc->verify($run->fresh())['ok']);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_a_zip_from_another_server_can_be_adopted_and_checked(): void
    {
        $this->makeBranch();
        $svc = app(BackupService::class);
        $run = $svc->run();
        $copy = sys_get_temp_dir().'/bk-'.uniqid().'.zip';
        copy($svc->path($run), $copy);
        $svc->delete($run);

        $adopted = $svc->adopt($copy);
        @unlink($copy);
        $this->assertSame('done', $adopted->status);
        $this->assertTrue($svc->verify($adopted, true)['ok']);
        $this->expectException(\RuntimeException::class);
        $svc->adopt('/nonexistent/file.zip');
    }

    public function test_retention_keeps_the_newest_good_backups(): void
    {
        $this->makeBranch();
        config(['backup.keep_days' => 10, 'backup.keep_min' => 2]);
        $svc = app(BackupService::class);
        $runs = [$svc->run(), $svc->run(), $svc->run()];
        BackupRun::query()->update(['created_at' => now()->subDays(40)]);
        $this->assertSame(1, $svc->prune());
        $this->assertCount(2, BackupRun::query()->get());
        $this->assertFalse(Storage::disk('local')->exists($runs[0]->file_path));
        $this->assertTrue(Storage::disk('local')->exists($runs[2]->file_path));
    }

    public function test_backups_page_is_for_the_platform_owner_only(): void
    {
        $owner = $this->owner();
        $branch = $this->makeBranch();
        $instAdmin = User::factory()->create(['institution_id' => $branch->institution_id]);
        $instAdmin->assignRole('institution-admin');
        $c = fn (string $p) => $this->centralUrl('super-admin/'.$p);

        $this->actingCentral($owner)->post($c('system/backups'))->assertSessionHasNoErrors();
        $run = BackupRun::query()->sole();
        $this->assertSame('done', $run->status, (string) $run->error);
        $this->actingCentral($owner)->get($c('system/backups'))->assertOk()->assertSee(__('tables'));
        $this->actingCentral($owner)->get($c("system/backups/{$run->id}/download"))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->actingCentral($owner)->post($c("system/backups/{$run->id}/verify"), ['drill' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($run->fresh()->drilled);
        $this->actingCentral($owner)->get($c('system/health'))->assertOk()->assertSee(__('Database'));
        $this->actingCentral($owner)->delete($c("system/backups/{$run->id}"))->assertSessionHasNoErrors();
        $this->assertSame(0, BackupRun::query()->count());

        foreach (['system/backups', 'system/health'] as $p) {
            $this->actingCentral($instAdmin)->get($c($p))->assertForbidden();
        }
        $this->actingCentral($instAdmin)->post($c('system/backups'))->assertForbidden();
    }

    public function test_health_checks_report_problems(): void
    {
        $this->makeBranch();
        $health = app(HealthService::class);
        $r = $health->run();
        $keys = collect($r['checks'])->pluck('status', 'key');
        $this->assertSame('ok', $keys['database']);
        $this->assertSame('ok', $keys['cache']);
        $this->assertContains($keys['storage'], ['ok', 'warn']);                      // warn only when this machine's disk is nearly full
        $this->assertSame('warn', $keys['scheduler']);                                 // no heartbeat yet
        $this->assertSame('warn', $keys['backup']);                                    // no backup yet
        $this->assertSame('warn', $keys['drill']);
        $this->assertSame('warn', $r['status']);

        Cache::put('monitor.heartbeat', time(), 600);
        $run = app(BackupService::class)->run();
        app(BackupService::class)->verify($run, true);
        $keys = collect($health->run()['checks'])->pluck('status', 'key');
        $this->assertSame(['ok', 'ok', 'ok'], [$keys['scheduler'], $keys['backup'], $keys['drill']]);

        Cache::put('monitor.heartbeat', time() - 3600, 600);
        $this->assertSame('fail', collect($health->run()['checks'])->pluck('status', 'key')['scheduler']);

        for ($i = 0; $i < 50; $i++) {
            DB::table('failed_jobs')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'x', 'queue' => 'x', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        }
        $this->assertSame('fail', collect($health->run()['checks'])->pluck('status', 'key')['queue']);
    }

    public function test_health_endpoint_needs_the_token_and_answers_503_on_failure(): void
    {
        $this->makeBranch();
        $url = $this->centralUrl('health/status');
        $this->get($url)->assertNotFound();                                            // HEALTH_TOKEN not set

        config(['monitor.token' => 'secret-token-123']);
        $this->get($url.'?token=wrong')->assertNotFound();
        $this->get($url.'?token=secret-token-123')->assertOk()->assertJsonPath('checks.database', 'ok');

        Cache::put('monitor.heartbeat', time() - 7200, 600);
        $this->get($url.'?token=secret-token-123')->assertStatus(503)->assertJsonPath('status', 'fail');
    }

    public function test_monitor_command_stores_the_result_and_fails_when_something_is_broken(): void
    {
        $this->makeBranch();
        Cache::put('monitor.heartbeat', time(), 600);
        $this->artisan('monitor:check')->assertSuccessful();
        $this->assertNotNull(Cache::get('monitor.last'));

        Cache::put('monitor.heartbeat', time() - 7200, 600);
        $this->artisan('monitor:check')->assertFailed();
    }

    public function test_backup_and_audit_commands_run(): void
    {
        $this->makeBranch();
        $this->artisan('backup:run --verify')->assertSuccessful();
        $this->artisan('backup:verify')->assertSuccessful();
        $this->artisan('backup:restore 1')->assertFailed();                            // refuses without --force
        $this->artisan('performance:audit')->assertSuccessful();
        $this->artisan('backup:verify 999')->assertFailed();
    }
}
