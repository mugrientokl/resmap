<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('contacto');
            $table->timestamps();
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('contacto_proveedor')->nullable();
        });

        DB::table('proveedores')->insert([
            ['nombre' => 'Imbra', 'contacto' => 'Ricardo Soto', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Brajovic', 'contacto' => 'Victoria Rodriguez', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proveedor_id');
            $table->dropColumn('contacto_proveedor');
        });
        Schema::dropIfExists('proveedores');
    }
};
