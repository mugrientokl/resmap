<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

#[Signature('app:backup-database')]
#[Description('Crea una copia de respaldo de la base de datos')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $directory = 'backups';
        $extension = config('database.default') === 'sqlite' ? 'sqlite' : 'sql';
        $filename = 'database-'.now()->format('Y-m-d_H-i-s').'.'.$extension;
        $disk->makeDirectory($directory);

        if (config('database.default') === 'sqlite') {
            $source = config('database.connections.sqlite.database');
            $target = $disk->path($directory.'/'.$filename);
            File::copy($source, $target);
        } else {
            $filename = 'database-'.now()->format('Y-m-d_H-i-s').'.sql';
            $path = $directory.'/'.$filename;
            $command = sprintf('mysqldump --host=%s --port=%s --user=%s --password=%s %s', escapeshellarg(env('DB_HOST')), escapeshellarg(env('DB_PORT', '3306')), escapeshellarg(env('DB_USERNAME')), escapeshellarg(env('DB_PASSWORD')), escapeshellarg(env('DB_DATABASE')));
            exec($command.' > '.escapeshellarg($disk->path($path)), $output, $status);
            if ($status !== 0) {
                $this->warn('mysqldump no está disponible; se creará un respaldo portable JSON.');
                File::delete($disk->path($path));
                $filename = 'database-'.now()->format('Y-m-d_H-i-s').'.json';
                $tables = collect(Schema::getTableListing())->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->get()->toArray()]);
                $disk->put($directory.'/'.$filename, json_encode($tables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        $files = collect($disk->files($directory))->sort()->values();
        $keep = config('filesystems.backup_keep');
        $disk->delete($files->take(max(0, $files->count() - $keep))->all());
        $this->info('Backup creado: '.$filename);

        return self::SUCCESS;
    }
}
