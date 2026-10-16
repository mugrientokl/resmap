<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index()
    {
        $backups = collect(Storage::disk('local')->files('backups'))
            ->sortDesc()
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'path' => $path,
                'size' => Storage::disk('local')->size($path),
                'date' => Storage::disk('local')->lastModified($path),
            ]);

        return view('backups.index', compact('backups'));
    }

    public function download(string $filename)
    {
        abort_unless(preg_match('/^database-[0-9_-]+\.(?:json|sql|sqlite)$/', $filename), 404);

        $path = 'backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $stream = Storage::disk('local')->readStream($path);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $filename);
    }
}
