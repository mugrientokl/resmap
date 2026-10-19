<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $obsoleteColumns = [
            'codigo_dte_temporal',
            'estado_dte',
            'track_id_sii',
            'xml_dte',
            'pdf_dte',
            'fecha_envio_sii',
            'fecha_respuesta_sii',
            'error_sii',
        ];
        $columns = array_values(array_filter(
            $obsoleteColumns,
            static fn (string $column): bool => Schema::hasColumn('ventas', $column),
        ));

        if ($columns !== []) {
            Schema::table('ventas', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
