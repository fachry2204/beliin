<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class BackupDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDirectory = storage_path('framework/testing/backups');
        config(['backup.directory' => $this->backupDirectory]);
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->admin->assignRole('Super Admin');
        CompanySetting::create(['company_name' => 'Backup Test', 'invoice_prefix' => 'INV']);
        File::deleteDirectory($this->backupDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDirectory);
        parent::tearDown();
    }

    public function test_super_admin_can_create_download_and_delete_database_backup(): void
    {
        $this->actingAs($this->admin)->post(route('company.backups.store'), ['type' => 'database'])
            ->assertRedirect()->assertSessionHas('success');

        $backup = app(BackupService::class)->all()[0];
        $this->assertSame('database', $backup['type']);
        $this->assertFalse($backup['automatic']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(app(BackupService::class)->path($backup['filename'])) === true);
        $sql = $zip->getFromName('database/database.sql');
        $zip->close();
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('company_settings', $sql);

        $this->actingAs($this->admin)->get(route('company.backups.download', $backup['filename']))
            ->assertOk()->assertDownload($backup['filename']);

        $this->actingAs($this->admin)->delete(route('company.backups.destroy', $backup['filename']))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame([], app(BackupService::class)->all());
    }

    public function test_super_admin_can_configure_automatic_backup(): void
    {
        $this->actingAs($this->admin)->put(route('company.backups.schedule'), [
            'backup_auto_enabled' => true,
            'backup_auto_type' => 'full',
            'backup_auto_frequency' => 'weekly',
            'backup_auto_time' => '02:30',
            'backup_retention_count' => 5,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('company_settings', [
            'backup_auto_enabled' => true,
            'backup_auto_type' => 'full',
            'backup_auto_frequency' => 'weekly',
            'backup_auto_time' => '02:30',
            'backup_retention_count' => 5,
        ]);
    }

    public function test_non_super_admin_cannot_access_backups(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $role->givePermissionTo('settings.manage');
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user)->post(route('company.backups.store'), ['type' => 'database'])->assertForbidden();
        $this->actingAs($user)->put(route('company.backups.schedule'), [
            'backup_auto_enabled' => true,
            'backup_auto_type' => 'database',
            'backup_auto_frequency' => 'daily',
            'backup_auto_time' => '01:00',
            'backup_retention_count' => 7,
        ])->assertForbidden();
    }

    public function test_scheduler_creates_automatic_zip_and_updates_last_run(): void
    {
        CompanySetting::query()->firstOrFail()->update([
            'backup_auto_enabled' => true,
            'backup_auto_type' => 'database',
            'backup_auto_frequency' => 'daily',
            'backup_auto_time' => now()->subMinute()->format('H:i'),
            'backup_retention_count' => 2,
        ]);

        $this->artisan('backup:run --automatic')->assertSuccessful();

        $backup = app(BackupService::class)->all()[0];
        $this->assertTrue($backup['automatic']);
        $this->assertSame('database', $backup['type']);
        $this->assertNotNull(CompanySetting::firstOrFail()->backup_last_run_at);
    }
}
