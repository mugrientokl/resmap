<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'KITS Y SETS',
            'descripcion' => 'Kits, sets y juegos de productos.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('productos')
            ->where(function ($query): void {
                $query->where('nombre', 'like', '%KIT%')
                    ->orWhere('nombre', 'like', '%SET%')
                    ->orWhere('nombre', 'like', '%JUEGO%');
            })
            ->update(['id_categoria' => $categoryId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $category = DB::table('categorias')->where('nombre_categoria', 'KITS Y SETS')->first();

        if ($category && ! DB::table('productos')->where('id_categoria', $category->id_categoria)->exists()) {
            DB::table('categorias')->where('id_categoria', $category->id_categoria)->delete();
        }
    }
};
