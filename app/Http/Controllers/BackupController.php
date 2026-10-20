<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(): View
    {
        $backups = collect(Storage::disk('local')->allFiles('backups'))
            ->sortDesc()
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'path' => $path,
                'size' => Storage::disk('local')->size($path),
                'date' => Storage::disk('local')->lastModified($path),
            ]);

        return view('backups.index', compact('backups'));
    }

    public function restore(Request $request, string $filename): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:RESTAURAR'],
        ]);

        abort_unless(preg_match('/^database-(?:baseline-)?[0-9_-]+\.zip$/', $filename), 404);

        $path = 'backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        try {
            $exitCode = Artisan::call('app:restore-database-backup', [
                'filename' => $filename,
                '--force' => true,
            ]);
        } catch (Throwable $exception) {
            Log::error('Falló la restauración desde la pantalla de backups.', [
                'filename' => $filename,
                'exception' => $exception,
            ]);

            return redirect()
                ->route('backups.index')
                ->with('error', 'No se pudo completar la restauración. La base puede no haber cambiado si el fallo ocurrió antes de iniciar la importación. Revisa el registro del sistema.');
        }

        if ($exitCode !== 0) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'La restauración terminó con un error. La base podría haber quedado parcialmente restaurada; revisa el registro del sistema y el backup preventivo.');
        }

        return redirect()
            ->route('backups.index')
            ->with('success', 'Backup restaurado correctamente. Se creó una copia preventiva del estado anterior.');
    }

    public function download(string $filename): StreamedResponse
    {
        abort_unless(preg_match('/^database-(?:baseline-)?[0-9_-]+\.(?:zip|json|sql|sqlite)$/', $filename), 404);

        $path = 'backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $stream = Storage::disk('local')->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo leer el archivo de backup solicitado.');
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $filename);
    }
}
