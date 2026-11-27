<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConfiguracionSistema
{
    public static function fondoActivo(): string
    {
        $valor = DB::table('configuraciones')->where('clave', 'fondo_sistema')->value('valor');

        return is_string($valor) ? $valor : 'fondo-1';
    }

    public static function fondoUrl(): string
    {
        $id = self::fondoActivo();
        $predeterminados = [
            'fondo-1' => asset('images/fondo-1.webp'),
            'fondo-2' => asset('images/fondo-2.webp'),
            'fondo-3' => asset('images/fondo-3.webp'),
            'fondo-4' => asset('images/fondo-4.webp'),
        ];
        if (isset($predeterminados[$id])) {
            return $predeterminados[$id];
        }
        if (str_starts_with($id, 'personalizado:')) {
            $ruta = DB::table('configuraciones')
                ->where('clave', 'fondo_personalizado:'.substr($id, strlen('personalizado:')))
                ->value('valor');
            if (is_string($ruta) && Storage::disk('public')->exists($ruta)) {
                return Storage::disk('public')->url($ruta);
            }
        }

        return $predeterminados['fondo-1'];
    }

    public static function logoActivo(): string
    {
        $valor = DB::table('configuraciones')->where('clave', 'logo_sistema')->value('valor');

        return is_string($valor) ? $valor : 'logo-renovado';
    }

    public static function logoUrl(): string
    {
        $id = self::logoActivo();
        if ($id === 'logo-resmap-2') {
            return asset('images/resmap-logo-2.png');
        }
        if ($id === 'logo-repuestos-electricos') {
            return asset('images/resmap-repuestos-electricos.png');
        }
        if ($id === 'logo-sin-fondo') {
            return asset('images/resmap sin fondo.png');
        }
        if (str_starts_with($id, 'personalizado:')) {
            $ruta = DB::table('configuraciones')
                ->where('clave', 'logo_personalizado:'.substr($id, strlen('personalizado:')))
                ->value('valor');
            if (is_string($ruta) && Storage::disk('public')->exists($ruta)) {
                return Storage::disk('public')->url($ruta);
            }
        }

        return asset('images/resmap-logo-renovado.png');
    }

    public static function logoRutaFisica(): ?string
    {
        $id = self::logoActivo();
        if ($id === 'logo-resmap-2') {
            return public_path('images/resmap-logo-2.png');
        }
        if ($id === 'logo-repuestos-electricos') {
            return public_path('images/resmap-repuestos-electricos.png');
        }
        if ($id === 'logo-sin-fondo') {
            return public_path('images/resmap sin fondo.png');
        }
        if (str_starts_with($id, 'personalizado:')) {
            $ruta = DB::table('configuraciones')
                ->where('clave', 'logo_personalizado:'.substr($id, strlen('personalizado:')))
                ->value('valor');
            if (is_string($ruta) && Storage::disk('public')->exists($ruta)) {
                return Storage::disk('public')->path($ruta);
            }
        }

        return public_path('images/resmap-logo-renovado.png');
    }

    public static function tamanoFuente(?object $usuario = null): string
    {
        $valor = $usuario !== null && isset($usuario->tamano_fuente)
            ? $usuario->tamano_fuente
            : 'mediana';

        return in_array($valor, ['pequena', 'mediana', 'grande'], true) ? (string) $valor : 'mediana';
    }

    public static function fuenteRaizPx(?object $usuario = null): int
    {
        return match (self::tamanoFuente($usuario)) {
            'pequena' => 14,
            'grande' => 18,
            default => 16,
        };
    }

    public static function notificacionActiva(string $tipo): bool
    {
        $valor = DB::table('configuraciones')
            ->where('clave', 'notificacion_'.$tipo)
            ->value('valor');

        return $valor === null || $valor === '1';
    }
}
