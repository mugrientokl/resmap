<?php

namespace App\Services\Facturacion;

use App\Models\Venta;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class LibreDteService
{
    public function emitir(Venta $venta): array
    {
        if (! config('services.libredte.enabled')) {
            return [
                'status' => 'disabled',
                'message' => 'Venta registrada. La emisión LibreDTE está desactivada.',
            ];
        }

        $mode = (string) config('services.libredte.mode', 'simulation');

        if ($mode === 'simulation') {
            return [
                'status' => 'simulated',
                'message' => 'Venta registrada. LibreDTE está en modo simulación y no se realizaron llamadas externas.',
            ];
        }

        if ($mode === 'production') {
            return [
                'status' => 'blocked',
                'message' => 'Venta registrada, pero LibreDTE está en modo producción y aún no se encuentra habilitado.',
            ];
        }

        if ($mode !== 'temporary') {
            return [
                'status' => 'simulated',
                'message' => 'Venta registrada. LibreDTE está en modo simulación y no se realizaron llamadas externas.',
            ];
        }

        if (config('services.libredte.environment') !== 'testing') {
            throw new \LogicException('LibreDTE solo puede ejecutarse en el ambiente testing durante esta etapa.');
        }

        try {
            $venta->update([
                'estado_dte' => 'enviando',
                'fecha_envio_sii' => now(),
                'error_sii' => null,
            ]);

            $temporal = $this->request()->post(
                $this->resource('documentos/emitir').'?'.http_build_query([
                    '_contribuyente_rut' => $this->rutEmisor(false),
                    '_contribuyente_certificacion' => 1,
                    'normalizar' => 1,
                    'links' => 1,
                    'email' => 0,
                    'formato' => 'json',
                ]),
                $this->payload($venta)
            );
            $temporal->throw();
            $temporalData = $temporal->json();
            $codigo = $this->findValue($temporalData, ['codigo', 'code']);

            if (! is_string($codigo) || $codigo === '') {
                throw new \RuntimeException('LibreDTE no devolvió el código del DTE temporal.');
            }

            $venta->update([
                'codigo_dte_temporal' => $codigo,
                'estado_dte' => 'temporal',
                'fecha_respuesta_sii' => now(),
            ]);

            return [
                'status' => 'temporary',
                'message' => 'Venta registrada y DTE temporal generado en LibreDTE.',
                'codigo_temporal' => $codigo,
            ];
        } catch (Throwable $exception) {
            $venta->update([
                'estado_dte' => 'error',
                'estado_sii' => 'Error',
                'error_sii' => Str::limit($exception->getMessage(), 1000),
                'fecha_respuesta_sii' => now(),
            ]);

            report($exception);

            return [
                'status' => 'error',
                'message' => 'Venta registrada, pero LibreDTE devolvió un error al generar el documento temporal.',
                'error' => $venta->error_sii,
            ];
        }
    }

    private function request(): PendingRequest
    {
        $apiKey = (string) config('services.libredte.api_key');

        if ($apiKey === '') {
            throw new \RuntimeException('Falta configurar LIBREDTE_API_KEY.');
        }

        $url = (string) config('services.libredte.url') ?: 'https://libredte.cl';

        return Http::baseUrl(rtrim($url, '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Authorization' => 'Basic '.$apiKey])
            ->timeout((int) config('services.libredte.timeout', 30));
    }

    private function payload(Venta $venta): array
    {
        $cliente = $venta->cliente;

        return [
            'Encabezado' => [
                'IdDoc' => ['TipoDTE' => $venta->codigo_dte],
                'Emisor' => ['RUTEmisor' => $this->rutEmisor(false)],
                'Receptor' => array_filter([
                    'RUTRecep' => $cliente?->rut,
                    'RznSocRecep' => $cliente?->razon_social ?: $cliente?->nombre,
                    'GiroRecep' => $cliente?->giro ?: 'Particular',
                    'DirRecep' => $cliente?->direccion,
                    'CmnaRecep' => $cliente?->comuna,
                    'CiudadRecep' => $cliente?->ciudad,
                ], static fn ($value): bool => $value !== null && $value !== ''),
            ],
            'Detalle' => $venta->detalles->map(static function ($detalle): array {
                $precioNeto = round((float) $detalle->precio_unitario / 1.19, 2);

                return [
                    'IndExe' => 0,
                    'NmbItem' => $detalle->producto?->nombre ?? $detalle->descripcion ?? 'Servicio',
                    'QtyItem' => (float) $detalle->cantidad,
                    'UnmdItem' => 'UN',
                    'PrcItem' => $precioNeto,
                    'MontoItem' => round($precioNeto * $detalle->cantidad, 0),
                ];
            })->values()->all(),
        ];
    }

    private function resource(string $path): string
    {
        return '/api/dte/'.$path;
    }

    private function rutEmisor(bool $withoutVerifier): string
    {
        $rut = (string) config('services.libredte.rut_emisor');

        if ($rut === '') {
            throw new \RuntimeException('Falta configurar LIBREDTE_RUT_EMISOR.');
        }

        return $withoutVerifier ? preg_replace('/[^0-9]/', '', explode('-', $rut)[0]) : $rut;
    }

    private function findValue(mixed $data, array $keys): mixed
    {
        if (! is_array($data)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            $found = $this->findValue($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
