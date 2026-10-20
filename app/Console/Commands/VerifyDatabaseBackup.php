<?php

namespace App\Console\Commands;

use App\Services\BackupArchive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

#[Signature('app:verify-database-backup {filename : Nombre del archivo ZIP de backup}')]
#[Description('Verifica la estructura y los checksums de un backup')]
class VerifyDatabaseBackup extends Command
{
    public function handle(BackupArchive $backupArchive): int
    {
        $filename = $this->argument('filename');

        if (! is_string($filename) || ! preg_match('/^database-(?:baseline-)?[0-9_-]+\.zip$/', $filename)) {
            $this->error('Nombre de backup no válido.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $path = 'backups/'.$filename;

        if (! $disk->exists($path)) {
            $this->error('El backup no existe.');

            return self::FAILURE;
        }

        try {
            $backupArchive->verify($disk->path($path), config('filesystems.backup_archive_password'));
        } catch (JsonException|RuntimeException $exception) {
            $this->error('Backup inválido: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup íntegro: todos los archivos coinciden con el manifiesto.');

        return self::SUCCESS;
    }
}
