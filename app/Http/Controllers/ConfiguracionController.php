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
        $seccion = request('seccion', 'ajustes-visuales');
        if (! in_array($seccion, ['ajustes-visuales', 'notificaciones'], true)) {
            $seccion = 'ajustes-visuales';
        }

        return view('configuracion.index', [
            'fondos' => $this->fondos(),
            'fondoActivo' => $this->fondoActivo(),
            'logos' => $this->logos(),
            'logoActivo' => $this->logoActivo(),
            'tamanoFuente' => $this->tamanoFuente(),
            'notificaciones' => $this->configuracionNotificaciones(),
            'seccion' => $seccion,
        ]);
    }

    public function actualizarLogo(Request $request): RedirectResponse
    {
        $idsValidos = collect($this->logos())->pluck('id')->all();
        $validated = $request->validate([
            'logo' => ['required', 'string', 'in:'.implode(',', $idsValidos)],
        ]);

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'logo_sistema'],
            ['valor' => $validated['logo']],
        );

        return back()->with('success', 'El logo del sistema fue actualizado.');
    }

    public function subirLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $archivo = $request->file('logo');
        if (! $archivo instanceof UploadedFile) {
            return back()->with('error', 'No se pudo leer el logo.');
        }

        $id = (string) Str::uuid();
        $nombre = 'logos-personalizados/'.$id.'.webp';
        $imagen = $this->crearImagenWebp($archivo);
        if ($imagen === null) {
            return back()->with('error', 'El logo no pudo convertirse a WebP.');
        }

        Storage::disk('public')->put($nombre, $imagen);
        DB::table('configuraciones')->insert([
            'clave' => 'logo_personalizado:'.$id,
            'valor' => $nombre,
        ]);
        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'logo_sistema'],
            ['valor' => 'personalizado:'.$id],
        );

        return back()->with('success', 'El logo personalizado fue agregado y activado.');
    }

    public function actualizarFuente(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tamano_fuente' => ['required', 'string', 'in:pequena,mediana,grande'],
        ]);

        $request->user()->update(['tamano_fuente' => $validated['tamano_fuente']]);

        return back()->with('success', 'El tamaño de fuente fue actualizado.');
    }

    public function actualizarNotificaciones(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notificaciones' => ['nullable', 'array'],
            'notificaciones.*' => ['string', 'in:solicitudes,cotizaciones,stock_critico'],
        ]);

        $activas = $validated['notificaciones'] ?? [];
        foreach (['solicitudes', 'cotizaciones', 'stock_critico'] as $tipo) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => 'notificacion_'.$tipo],
                ['valor' => in_array($tipo, $activas, true) ? '1' : '0'],
            );
        }

        return back()->with('success', 'La configuración de notificaciones fue actualizada.');
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

    private function logos(): array
    {
        $logos = [
            ['id' => 'logo-renovado', 'nombre' => 'Logo renovado', 'url' => asset('images/resmap-logo-renovado.png')],
            ['id' => 'logo-sin-fondo', 'nombre' => 'Logo transparente', 'url' => asset('images/resmap sin fondo.png')],
            ['id' => 'logo-resmap-2', 'nombre' => 'RESMAP Logo 2', 'url' => asset('images/resmap-logo-2.png')],
            ['id' => 'logo-repuestos-electricos', 'nombre' => 'RESMAP Repuestos Eléctricos', 'url' => asset('images/resmap-repuestos-electricos.png')],
        ];

        $personalizados = DB::table('configuraciones')
            ->where('clave', 'like', 'logo_personalizado:%')
            ->orderBy('clave')
            ->get();

        foreach ($personalizados as $personalizado) {
            $id = substr($personalizado->clave, strlen('logo_personalizado:'));
            $logos[] = [
                'id' => 'personalizado:'.$id,
                'nombre' => 'Logo personalizado',
                'url' => Storage::disk('public')->url($personalizado->valor),
            ];
        }

        return $logos;
    }

    private function logoActivo(): string
    {
        $id = DB::table('configuraciones')->where('clave', 'logo_sistema')->value('valor');

        return is_string($id) && collect($this->logos())->contains('id', $id)
            ? $id
            : 'logo-renovado';
    }

    private function tamanoFuente(): string
    {
        $valor = auth()->user()?->tamano_fuente;

        return in_array($valor, ['pequena', 'mediana', 'grande'], true)
            ? (string) $valor
            : 'mediana';
    }

    private function configuracionNotificaciones(): array
    {
        $tipos = [
            'solicitudes' => 'Solicitudes y servicios web',
            'cotizaciones' => 'Cotizaciones pendientes',
            'stock_critico' => 'Stock crítico de productos',
        ];

        foreach ($tipos as $tipo => $nombre) {
            $valor = DB::table('configuraciones')->where('clave', 'notificacion_'.$tipo)->value('valor');
            $tipos[$tipo] = [
                'nombre' => $nombre,
                'activo' => $valor === null || $valor === '1',
            ];
        }

        return $tipos;
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
