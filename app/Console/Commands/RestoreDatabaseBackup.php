<?php

namespace App\Console\Commands;

use App\Services\BackupArchive;
use App\Support\DatabaseClientProcessEnvironment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

#[Signature('app:restore-database-backup {filename : Nombre del archivo ZIP de backup} {--force : Confirma el reemplazo de los datos actuales}')]
#[Description('Restaura la base de datos y los archivos subidos desde un backup verificado')]
class RestoreDatabaseBackup extends Command
{
    public function handle(BackupArchive $backupArchive): int
    {
        if (! $this->option('force')) {
            $this->error('La restauración reemplazará los datos actuales. Usa --force para confirmar.');

            return self::FAILURE;
        }

        $filename = $this->argument('filename');

        if (! is_string($filename) || ! preg_match('/^database-(?:baseline-)?[0-9_-]+\.zip$/', $filename)) {
            $this->error('Nombre de backup no válido.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $relativePath = 'backups/'.$filename;

        if (! $disk->exists($relativePath)) {
            $this->error('El backup no existe.');

            return self::FAILURE;
        }

        $archivePath = $disk->path($relativePath);
        $manifest = $backupArchive->verify($archivePath, config('filesystems.backup_archive_password'));
        $currentDriver = DB::connection()->getDriverName();
        $isCompatibleDriver = $currentDriver === $manifest['driver']
            || (in_array($currentDriver, ['mysql', 'mariadb'], true)
                && in_array($manifest['driver'], ['mysql', 'mariadb'], true));

        if (! $isCompatibleDriver) {
            $this->error('El driver del backup no coincide con el driver de la base actual.');

            return self::FAILURE;
        }

        if ($currentDriver === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            $this->error('No se pueden restaurar backups sobre una base SQLite en memoria.');

            return self::FAILURE;
        }

        $backupStatus = Artisan::call('app:backup-database', ['--baseline' => true]);

        if ($backupStatus !== self::SUCCESS) {
            $this->error('No se pudo crear el backup de seguridad previo a la restauración.');
            $this->line(Artisan::output());

            return self::FAILURE;
        }

        $workDirectory = storage_path('app/private/restore-work/'.Str::uuid());
        File::ensureDirectoryExists($workDirectory);
        $archive = new ZipArchive;

        try {
            if ($archive->open($archivePath) !== true) {
                throw new RuntimeException('No se pudo abrir el archivo de backup.');
            }

            $password = config('filesystems.backup_archive_password');

            if (is_string($password) && $password !== '') {
                $archive->setPassword($password);
            }

            if ($currentDriver === 'sqlite') {
                $this->restoreSqlite($archive, $workDirectory);
            } else {
                $this->restoreMysql($archive);
            }

            $this->restoreUploads($archive, $manifest['files']);
            $this->info('Base de datos y archivos restaurados desde '.$filename.'.');
        } finally {
            $archive->close();
            File::deleteDirectory($workDirectory);
        }

        return self::SUCCESS;
    }

    private function restoreSqlite(ZipArchive $archive, string $workDirectory): void
    {
        $stream = $archive->getStream('database.sqlite');

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo leer la base SQLite incluida en el backup.');
        }

        $snapshotPath = $workDirectory.DIRECTORY_SEPARATOR.'database.sqlite';
        $snapshot = fopen($snapshotPath, 'wb');

        if (! is_resource($snapshot)) {
            fclose($stream);
            throw new RuntimeException('No se pudo preparar la restauración SQLite.');
        }

        try {
            if (stream_copy_to_stream($stream, $snapshot) === false) {
                throw new RuntimeException('No se pudo extraer la base SQLite del backup.');
            }
        } finally {
            fclose($stream);
            fclose($snapshot);
        }

        $targetPath = config('database.connections.sqlite.database');
        DB::disconnect();

        if (! File::copy($snapshotPath, $targetPath)) {
            throw new RuntimeException('No se pudo reemplazar la base SQLite actual.');
        }
    }

    private function restoreMysql(ZipArchive $archive): void
    {
        $stream = $archive->getStream('database.sql');

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo leer el dump SQL incluido en el backup.');
        }

        $connection = DB::connection()->getConfig();
        $command = [config('filesystems.mysql_path')];

        if (! empty($connection['unix_socket'])) {
            $command[] = '--socket='.$connection['unix_socket'];
        } else {
            $command[] = '--host='.$connection['host'];
            $command[] = '--port='.$connection['port'];
        }

        if (! empty($connection['username'])) {
            $command[] = '--user='.$connection['username'];
        }

        $command[] = '--default-character-set='.$connection['charset'];
        $command[] = $connection['database'];

        $environment = app(DatabaseClientProcessEnvironment::class)->forPassword($connection['password']);
        $process = new Process($command, base_path(), $environment, $stream, 600);

        try {
            $process->run();
        } finally {
            fclose($stream);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Falló la restauración MySQL: '.trim($process->getErrorOutput()));
        }
    }

    /**
     * @param  array<string, array{sha256: string, size: int}>  $files
     */
    private function restoreUploads(ZipArchive $archive, array $files): void
    {
        $disk = Storage::disk('public');
        $existingFiles = $disk->allFiles();

        if ($existingFiles !== [] && ! $disk->delete($existingFiles)) {
            throw new RuntimeException('No se pudieron reemplazar los archivos subidos actuales.');
        }

        foreach (array_keys($files) as $entryName) {
            if (! str_starts_with($entryName, 'uploads/')) {
                continue;
            }

            $stream = $archive->getStream($entryName);

            if (! is_resource($stream)) {
                throw new RuntimeException('No se pudo leer un archivo subido del backup: '.$entryName);
            }

            try {
                if (! $disk->put(substr($entryName, strlen('uploads/')), $stream)) {
                    throw new RuntimeException('No se pudo restaurar un archivo subido: '.$entryName);
                }
            } finally {
                fclose($stream);
            }
        }
    }
}
