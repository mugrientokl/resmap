<?php

namespace Tests\Feature;

use App\Support\DatabaseClientProcessEnvironment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;
use ZipArchive;

class BackupDatabaseTest extends TestCase
{
    private string $localStorageRoot;

    private string $publicStorageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->localStorageRoot = storage_path('framework/testing/backup-local/'.Str::uuid());
        $this->publicStorageRoot = storage_path('framework/testing/backup-public/'.Str::uuid());

        config([
            'filesystems.backup_archive_password' => null,
            'filesystems.backup_keep' => 14,
            'filesystems.backup_secondary_path' => null,
            'filesystems.disks.local.root' => $this->localStorageRoot,
            'filesystems.disks.public.root' => $this->publicStorageRoot,
        ]);

        Storage::forgetDisk('local');
        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->localStorageRoot);
        File::deleteDirectory($this->publicStorageRoot);

        parent::tearDown();
    }

    public function test_database_client_process_environment_keeps_password_out_of_command_arguments(): void
    {
        $environment = app(DatabaseClientProcessEnvironment::class)->forPassword('test-password');

        $this->assertSame('test-password', $environment['MYSQL_PWD']);

        if (DIRECTORY_SEPARATOR === '\\') {
            $this->assertTrue(isset($environment['SystemRoot']) || isset($environment['WINDIR']));
        }
    }

    public function test_it_creates_and_verifies_a_complete_baseline_archive(): void
    {
        config(['filesystems.backup_archive_password' => 'test-backup-key-2026']);
        DB::statement('CREATE TABLE backup_probe (value TEXT NOT NULL)');
        DB::table('backup_probe')->insert(['value' => 'preserved']);
        Storage::disk('public')->put('productos/repuesto.jpg', 'image-data');

        $this->artisan('app:backup-database', ['--baseline' => true])
            ->assertSuccessful();

        $backups = Storage::disk('local')->files('backups');

        $this->assertCount(1, $backups);
        $filename = basename($backups[0]);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('local')->path($backups[0])));
        $archive->setPassword('test-backup-key-2026');

        $manifest = json_decode($archive->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $databaseContents = $archive->getFromName('database.sqlite');
        $archive->close();

        $this->assertSame('sqlite', $manifest['driver']);
        $this->assertArrayHasKey('database.sqlite', $manifest['files']);
        $this->assertArrayHasKey('uploads/productos/repuesto.jpg', $manifest['files']);
        $this->assertIsString($databaseContents);

        $snapshotPath = tempnam(sys_get_temp_dir(), 'resmap-backup-');
        $this->assertIsString($snapshotPath);
        file_put_contents($snapshotPath, $databaseContents);
        $snapshot = new PDO('sqlite:'.$snapshotPath);
        $this->assertSame('preserved', $snapshot->query('SELECT value FROM backup_probe')->fetchColumn());
        unset($snapshot);
        unlink($snapshotPath);

        $this->artisan('app:verify-database-backup', ['filename' => $filename])
            ->assertSuccessful()
            ->expectsOutput('Backup íntegro: todos los archivos coinciden con el manifiesto.');
    }

    public function test_it_rejects_an_archive_when_a_file_checksum_does_not_match(): void
    {
        DB::statement('CREATE TABLE backup_probe (value TEXT NOT NULL)');
        DB::table('backup_probe')->insert(['value' => 'preserved']);
        Storage::disk('public')->put('productos/repuesto.jpg', 'image-data');

        $this->artisan('app:backup-database', ['--baseline' => true])
            ->assertSuccessful();

        $path = Storage::disk('local')->files('backups')[0];
        $archive = new ZipArchive;
        $archive->open(Storage::disk('local')->path($path));
        $archive->deleteName('uploads/productos/repuesto.jpg');
        $archive->addFromString('uploads/productos/repuesto.jpg', 'tampered-image');
        $archive->close();

        $this->artisan('app:verify-database-backup', ['filename' => basename($path)])
            ->assertExitCode(1)
            ->expectsOutput('Backup inválido: No coincide la integridad del archivo: uploads/productos/repuesto.jpg');
    }

    public function test_it_restores_the_database_and_uploaded_files_after_creating_a_safety_backup(): void
    {
        DB::statement('CREATE TABLE backup_probe (value TEXT NOT NULL)');
        DB::table('backup_probe')->insert(['value' => 'preserved']);
        Storage::disk('public')->put('productos/repuesto.jpg', 'image-data');

        $this->artisan('app:backup-database', ['--baseline' => true])
            ->assertSuccessful();

        $backup = basename(Storage::disk('local')->files('backups')[0]);
        $targetPath = tempnam(sys_get_temp_dir(), 'resmap-restore-');
        $this->assertIsString($targetPath);
        config(['database.connections.sqlite.database' => $targetPath]);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE restore_probe (value TEXT NOT NULL)');
        DB::table('restore_probe')->insert(['value' => 'replace-me']);
        Storage::disk('public')->put('productos/extra.jpg', 'remove-me');

        $this->artisan('app:restore-database-backup', [
            'filename' => $backup,
            '--force' => true,
        ])->assertSuccessful();

        DB::purge('sqlite');
        $this->assertDatabaseHas('backup_probe', ['value' => 'preserved']);
        $this->assertFalse(Schema::hasTable('restore_probe'));
        $this->assertSame('image-data', Storage::disk('public')->get('productos/repuesto.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('productos/extra.jpg'));
        $this->assertCount(2, Storage::disk('local')->files('backups'));

        DB::disconnect('sqlite');
        File::delete($targetPath);
    }
}
