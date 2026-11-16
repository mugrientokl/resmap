<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministracionController extends Controller
{
    public function index(): View
    {
        $posicionNavegacion = DB::table('configuraciones')->where('clave', 'posicion_navegacion')->value('valor') ?? 'superior';

        return view('administracion.index', compact('posicionNavegacion'));
    }

    public function actualizarNavegacion(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'posicion_navegacion' => ['required', Rule::in(['superior', 'izquierda'])],
        ]);

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'posicion_navegacion'],
            ['valor' => $datos['posicion_navegacion']],
        );

        return redirect()->route('administracion.index')->with('success', 'Configuración de navegación guardada.');
    }
}
