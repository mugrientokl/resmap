<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConfiguracionController extends Controller
{
    public function index(): View
    {
        return view('configuracion.index', [
            'fondos' => $this->fondos(),
            'fondoActivo' => $this->fondoActivo(),
        ]);
    }

    public function actualizarFondo(Request $request): RedirectResponse
    {
        $idsValidos = collect($this->fondos())->pluck('id')->all();
        $validated = $request->validate([
            'fondo' => ['required', 'string', 'in:'.implode(',', $idsValidos)],
        ], [
            'fondo.required' => 'Selecciona un fondo.',
            'fondo.in' => 'El fondo seleccionado no está disponible.',
        ]);

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'fondo_sistema'],
            ['valor' => $validated['fondo']],
        );

        return back()->with('success', 'El fondo del sistema fue actualizado.');
    }

    public function subirFondo(Request $request): RedirectResponse
    {
        $request->validate([
            'fondo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'fondo.required' => 'Selecciona una imagen.',
            'fondo.mimes' => 'La imagen debe estar en formato JPG, PNG o WebP.',
            'fondo.max' => 'La imagen no puede superar los 10 MB.',
        ]);

        $archivo = $request->file('fondo');
        if (! $archivo instanceof UploadedFile) {
            return back()->with('error', 'No se pudo leer la imagen.');
        }

        $id = (string) Str::uuid();
        $nombre = 'fondos-personalizados/'.$id.'.webp';
        $imagen = $this->crearImagenWebp($archivo);

        if ($imagen === null) {
            return back()->with('error', 'La imagen no pudo convertirse a WebP.');
        }

        Storage::disk('public')->put($nombre, $imagen);
        DB::table('configuraciones')->insert([
            'clave' => 'fondo_personalizado:'.$id,
            'valor' => $nombre,
        ]);

        return back()->with('success', 'El fondo personalizado fue agregado.');
    }

    public function eliminarFondo(string $id): RedirectResponse
    {
        abort_unless(preg_match('/^[a-f0-9-]{36}$/i', $id), 404);

        $clave = 'fondo_personalizado:'.$id;
        $ruta = DB::table('configuraciones')->where('clave', $clave)->value('valor');
        abort_if(! is_string($ruta), 404);

        if (DB::table('configuraciones')->where('clave', 'fondo_sistema')->value('valor') === 'personalizado:'.$id) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => 'fondo_sistema'],
                ['valor' => 'fondo-1'],
            );
        }

        DB::table('configuraciones')->where('clave', $clave)->delete();
        Storage::disk('public')->delete($ruta);

        return back()->with('success', 'El fondo personalizado fue eliminado.');
    }

    private function fondos(): array
    {
        $fondos = [
            ['id' => 'fondo-1', 'nombre' => 'Fondo 1', 'url' => asset('images/fondo-1.webp')],
            ['id' => 'fondo-2', 'nombre' => 'Fondo 2', 'url' => asset('images/fondo-2.webp')],
            ['id' => 'fondo-3', 'nombre' => 'Fondo 3', 'url' => asset('images/fondo-3.webp')],
            ['id' => 'fondo-4', 'nombre' => 'Fondo 4', 'url' => asset('images/fondo-4.webp')],
        ];

        $personalizados = DB::table('configuraciones')
            ->where('clave', 'like', 'fondo_personalizado:%')
            ->orderBy('clave')
            ->get();

        $numero = 1;
        foreach ($personalizados as $personalizado) {
            $id = substr($personalizado->clave, strlen('fondo_personalizado:'));
            $fondos[] = [
                'id' => 'personalizado:'.$id,
                'nombre' => 'Fondo personalizado '.$numero,
                'url' => Storage::disk('public')->url($personalizado->valor),
            ];
            $numero++;
        }

        return $fondos;
    }

    private function fondoActivo(): string
    {
        $id = DB::table('configuraciones')->where('clave', 'fondo_sistema')->value('valor');

        return is_string($id) && collect($this->fondos())->contains('id', $id)
            ? $id
            : 'fondo-1';
    }

    private function crearImagenWebp(UploadedFile $archivo): ?string
    {
        $tipo = (string) $archivo->getMimeType();
        $ruta = $archivo->getRealPath();

        $imagen = match ($tipo) {
            'image/jpeg' => imagecreatefromjpeg($ruta),
            'image/png' => imagecreatefrompng($ruta),
            'image/webp' => imagecreatefromwebp($ruta),
            default => null,
        };

        if (! $imagen instanceof \GdImage) {
            return null;
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        if ($ancho > 1920) {
            $nuevoAncho = 1920;
            $nuevoAlto = (int) round($alto * $nuevoAncho / $ancho);
            $redimensionada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
            imagealphablending($redimensionada, false);
            imagesavealpha($redimensionada, true);
            imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
            imagedestroy($imagen);
            $imagen = $redimensionada;
        }

        ob_start();
        $convertida = imagewebp($imagen, null, 82);
        $contenido = ob_get_clean();
        imagedestroy($imagen);

        return $convertida ? $contenido : null;
    }
}
