<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoImportado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoRealSeeder extends Seeder
{
    private const COLUMN_COUNT = 10;

    public function run(): void
    {
        $path = database_path('seeders/csv/LISTADO DE PRECIOS RESMAP.csv');

        if (! file_exists($path)) {
            $this->command->error("No se encontró el archivo CSV en: {$path}");

            return;
        }

        $file = fopen($path, 'rb');

        if ($file === false) {
            $this->command->error('No fue posible abrir el archivo CSV.');

            return;
        }

        $archivoOrigen = basename($path);
        $cabeceraEncontrada = false;
        $filaOrigen = 0;
        $importados = 0;
        $revisar = 0;

        DB::transaction(function () use ($file, $archivoOrigen, &$cabeceraEncontrada, &$filaOrigen, &$importados, &$revisar): void {
            Producto::where(function ($query): void {
                $query->where('codigo_barra', 'like', 'KIT-%')
                    ->orWhere('codigo_barra', 'like', 'SET-%')
                    ->orWhere('codigo_barra', 'like', 'JUEGO-%');
            })->delete();

            while (($linea = fgets($file)) !== false) {
                $filaOrigen++;
                $linea = $this->normalizarCodificacion($linea);
                $row = str_getcsv($linea, ';');

                if (! $cabeceraEncontrada) {
                    if ($this->esCabecera($row)) {
                        $cabeceraEncontrada = true;
                        $this->command->line('Cabecera detectada en la fila '.$filaOrigen.'.');
                    }

                    continue;
                }

                if ($this->filaVacia($row)) {
                    continue;
                }

                $row = array_pad(array_slice($row, 0, self::COLUMN_COUNT), self::COLUMN_COUNT, null);
                $detalle = $this->texto($row[3]);
                $codigoOrigen = $this->texto($row[4]);
                $categoriaOrigen = $this->texto($row[7]);
                $precioIvaOrigen = $this->texto($row[6]);
                $precioNetoOrigen = $this->texto($row[8]);
                $stockOrigen = $this->texto($row[2]);
                $estado = $this->estadoDeFila($detalle, $precioIvaOrigen, $categoriaOrigen, $stockOrigen);

                ProductoImportado::updateOrCreate(
                    ['archivo_origen' => $archivoOrigen, 'fila_origen' => $filaOrigen],
                    [
                        'it' => $this->texto($row[0]),
                        'ubicacion' => $this->texto($row[1]),
                        'stock_origen' => $stockOrigen,
                        'detalle' => $detalle,
                        'codigo_origen' => $codigoOrigen,
                        'unidad' => $this->texto($row[5]),
                        'precio_iva_origen' => $precioIvaOrigen,
                        'categoria_origen' => $categoriaOrigen,
                        'precio_neto_origen' => $precioNetoOrigen,
                        'datos_originales' => $row,
                        'estado' => $estado,
                        'observaciones' => $this->observaciones($detalle, $precioIvaOrigen, $categoriaOrigen, $stockOrigen),
                    ]
                );

                $categoriaNombre = $this->esKitOSet($detalle)
                    ? 'KITS Y SETS'
                    : ($categoriaOrigen ?: 'GENERAL');

                $categoria = Categoria::firstOrCreate(
                    ['nombre_categoria' => $categoriaNombre],
                    ['descripcion' => 'Categoría importada desde inventario RESMAP']
                );

                Producto::updateOrCreate(
                    ['codigo_barra' => $this->codigoInterno($filaOrigen)],
                    [
                        'codigo_origen' => $codigoOrigen,
                        'nombre' => $detalle ?: 'Producto sin nombre - fila '.$filaOrigen,
                        'descripcion' => 'Importado desde '.$archivoOrigen.' | Fila '.$filaOrigen,
                        'precio' => $this->precio($precioIvaOrigen),
                        'stock' => $this->stock($stockOrigen),
                        'stock_critico' => 3,
                        'id_categoria' => $categoria->id_categoria,
                        'ubicacion' => $this->texto($row[1]),
                        'unidad' => $this->texto($row[5]),
                        'fila_origen' => $filaOrigen,
                        'estado_importacion' => $estado,
                    ]
                );

                $importados++;
                $revisar += $estado === 'revisar' ? 1 : 0;
            }
        });

        fclose($file);

        if (! $cabeceraEncontrada) {
            $this->command->error('No se encontró la cabecera esperada del CSV.');

            return;
        }

        $this->command->info("Filas importadas: {$importados}");
        $this->command->warn("Filas que requieren revisión: {$revisar}");
        $this->command->info('Las filas originales quedaron guardadas en productos_importados.');
    }

    private function esCabecera(array $row): bool
    {
        $valores = array_map(fn ($value): string => strtoupper($this->texto($value) ?? ''), $row);

        return ($valores[0] ?? '') === 'IT'
            && in_array('STOCK', $valores, true)
            && in_array('DETALLE', $valores, true);
    }

    private function filaVacia(array $row): bool
    {
        return count(array_filter($row, fn ($value): bool => $this->texto($value) !== null)) === 0;
    }

    private function normalizarCodificacion(string $linea): string
    {
        $linea = preg_replace('/^\xEF\xBB\xBF/', '', $linea) ?? $linea;

        if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
            $encoding = mb_detect_encoding($linea, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

            if ($encoding !== false && $encoding !== 'UTF-8') {
                $linea = mb_convert_encoding($linea, 'UTF-8', $encoding);
            }
        }

        return $linea;
    }

    private function texto(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function precio(?string $value): float
    {
        $value = $this->texto($value);

        if ($value === null) {
            return 0;
        }

        $value = str_replace(['$', ' ', "\xc2\xa0"], '', $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function stock(?string $value): int
    {
        $value = $this->texto($value);

        return $value !== null && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : 0;
    }

    private function codigoInterno(int $filaOrigen): string
    {
        return sprintf('RESMAP-IMPORT-%06d', $filaOrigen);
    }

    private function esKitOSet(?string $detalle): bool
    {
        return $detalle !== null
            && preg_match('/\b(KIT|SET|JUEGO)\b/u', strtoupper($detalle)) === 1;
    }

    private function estadoDeFila(?string $detalle, ?string $precio, ?string $categoria, ?string $stock): string
    {
        return $detalle === null || $precio === null || $categoria === null || $stock === null
            ? 'revisar'
            : 'validado';
    }

    private function observaciones(?string $detalle, ?string $precio, ?string $categoria, ?string $stock): ?string
    {
        $observaciones = [];

        if ($detalle === null) {
            $observaciones[] = 'Falta detalle/nombre';
        }

        if ($precio === null) {
            $observaciones[] = 'Falta precio con IVA';
        }

        if ($categoria === null) {
            $observaciones[] = 'Falta categoría';
        }

        if ($stock === null) {
            $observaciones[] = 'Stock vacío, se importó como 0';
        }

        return $observaciones === [] ? null : implode('; ', $observaciones);
    }
}
