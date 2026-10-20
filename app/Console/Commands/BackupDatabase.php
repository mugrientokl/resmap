<?php

namespace App\Console\Commands;

use App\Services\BackupArchive;
use App\Support\DatabaseClientProcessEnvironment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

#[Signature('app:backup-database {--baseline : Conserva este backup fuera de la rotación diaria}')]
#[Description('Crea y verifica un backup completo de la base de datos y archivos subidos')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        if ((app()->isProduction() || config('filesystems.backup_secondary_path'))
            && blank(config('filesystems.backup_archive_password'))) {
            throw new RuntimeException('Configura BACKUP_ARCHIVE_PASSWORD antes de crear backups en producción o en una segunda ubicación.');
        }

        $workDirectory = storage_path('app/private/backup-work/'.Str::uuid());
        File::ensureDirectoryExists($workDirectory);

        try {
            $driver = DB::connection()->getDriverName();
            $databaseFile = $driver === 'sqlite' ? 'database.sqlite' : 'database.sql';
            $databasePath = $workDirectory.DIRECTORY_SEPARATOR.$databaseFile;
            $this->createDatabaseSnapshot($driver, $databasePath);

            $timestamp = now()->format('Y-m-d_H-i-s-u');
            $filename = 'database-'.($this->option('baseline') ? 'baseline-' : '').$timestamp.'.zip';
            $archivePath = $workDirectory.DIRECTORY_SEPARATOR.$filename;
            $manifest = [
                'format_version' => 1,
                'created_at' => now()->toIso8601String(),
                'driver' => $driver,
                'database_file' => $databaseFile,
                'files' => [],
            ];

            $this->createArchive($archivePath, $databasePath, $databaseFile, $manifest);
            app(BackupArchive::class)->verify($archivePath, config('filesystems.backup_archive_password'));

            $localDisk = Storage::disk('local');
            $localPath = 'backups/'.$filename;
            File::ensureDirectoryExists(dirname($localDisk->path($localPath)));

            if (! File::copy($archivePath, $localDisk->path($localPath))) {
                throw new RuntimeException('No se pudo guardar el backup en el almacenamiento local.');
            }

            $this->copyToSecondaryDisk($archivePath, $filename);
            $this->pruneBackups('local');

            if (config('filesystems.backup_secondary_path')) {
                $this->pruneBackups('backup_secondary');
            }

            $this->info('Backup completo, verificado y guardado: '.$filename);

            return self::SUCCESS;
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    private function createDatabaseSnapshot(string $driver, string $target): void
    {
        if ($driver === 'sqlite') {
            $pdo = DB::connection()->getPdo();

            if ($pdo->exec('VACUUM INTO '.$pdo->quote($target)) === false) {
                throw new RuntimeException('No se pudo crear una copia consistente de SQLite.');
            }

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('El driver de base de datos no está soportado: '.$driver);
        }

        $connection = DB::connection()->getConfig();
        $command = [
            config('filesystems.mysqldump_path'),
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--triggers',
            '--hex-blob',
            '--add-drop-table',
            '--default-character-set='.$connection['charset'],
            '--result-file='.$target,
        ];

        if (! empty($connection['unix_socket'])) {
            $command[] = '--socket='.$connection['unix_socket'];
        } else {
            $command[] = '--host='.$connection['host'];
            $command[] = '--port='.$connection['port'];
        }

        if (! empty($connection['username'])) {
            $command[] = '--user='.$connection['username'];
        }

        $command[] = $connection['database'];

        $environment = app(DatabaseClientProcessEnvironment::class)->forPassword($connection['password']);
        $process = new Process($command, base_path(), $environment, null, 600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Falló mysqldump: '.trim($process->getErrorOutput()));
        }

        if (! File::exists($target) || File::size($target) === 0) {
            throw new RuntimeException('mysqldump terminó sin generar un archivo de base de datos.');
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function createArchive(string $archivePath, string $databasePath, string $databaseFile, array $manifest): void
    {
        $archive = new ZipArchive;
        $opened = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL);

        if ($opened !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP del backup.');
        }

        $closed = false;

        try {
            $password = config('filesystems.backup_archive_password');

            if (is_string($password) && $password !== '' && ! $archive->setPassword($password)) {
                throw new RuntimeException('No se pudo habilitar el cifrado del backup.');
            }

            $this->addFileToArchive($archive, $databasePath, $databaseFile, $manifest['files'], $password);

            foreach (Storage::disk('public')->allFiles() as $relativePath) {
                $source = Storage::disk('public')->path($relativePath);
                $entryName = 'uploads/'.str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                $this->addFileToArchive($archive, $source, $entryName, $manifest['files'], $password);
            }

            $manifestContents = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if (! $archive->addFromString('manifest.json', $manifestContents)) {
                throw new RuntimeException('No se pudo agregar el manifiesto al backup.');
            }

            if (is_string($password) && $password !== ''
                && ! $archive->setEncryptionName('manifest.json', ZipArchive::EM_AES_256)) {
                throw new RuntimeException('No se pudo cifrar el manifiesto del backup.');
            }

            if (! $archive->close()) {
                throw new RuntimeException('No se pudo finalizar el archivo ZIP del backup.');
            }

            $closed = true;
        } finally {
            if (! $closed) {
                $archive->close();
            }
        }
    }

    /**
     * @param  array<string, array{sha256: string, size: int}>  $files
     */
    private function addFileToArchive(ZipArchive $archive, string $source, string $entryName, array &$files, mixed $password): void
    {
        if (! File::exists($source) || ! $archive->addFile($source, $entryName)) {
            throw new RuntimeException('No se pudo agregar al backup: '.$entryName);
        }

        if (is_string($password) && $password !== ''
            && ! $archive->setEncryptionName($entryName, ZipArchive::EM_AES_256)) {
            throw new RuntimeException('No se pudo cifrar el archivo del backup: '.$entryName);
        }

        $checksum = hash_file('sha256', $source);

        if (! is_string($checksum)) {
            throw new RuntimeException('No se pudo calcular el checksum de: '.$entryName);
        }

        $files[$entryName] = [
            'sha256' => $checksum,
            'size' => File::size($source),
        ];
    }

    private function copyToSecondaryDisk(string $archivePath, string $filename): void
    {
        if (! config('filesystems.backup_secondary_path')) {
            return;
        }

        $disk = Storage::disk('backup_secondary');
        $disk->makeDirectory('backups');
        $stream = fopen($archivePath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo abrir el backup para copiarlo a la segunda ubicación.');
        }

        try {
            if (! $disk->writeStream('backups/'.$filename, $stream)) {
                throw new RuntimeException('No se pudo copiar el backup a la segunda ubicación.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function pruneBackups(string $diskName): void
    {
        $disk = Storage::disk($diskName);
        $dailyBackups = collect($disk->files('backups'))
            ->filter(fn (string $path): bool => str_ends_with(basename($path), '.zip')
                && ! str_starts_with(basename($path), 'database-baseline-'))
            ->sort()
            ->values();
        $keep = max(0, (int) config('filesystems.backup_keep'));
        $expired = $dailyBackups->take(max(0, $dailyBackups->count() - $keep))->all();

        if ($expired !== [] && ! $disk->delete($expired)) {
            throw new RuntimeException('No se pudieron eliminar los backups vencidos de '.$diskName.'.');
        }
    }
}
