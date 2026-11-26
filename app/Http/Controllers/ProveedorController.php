<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Rules\CorreoConDominio;
use App\Support\TelefonoChileno;
use App\Support\TextoPersona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    public function index(): View
    {
        $proveedores = Proveedor::orderBy('nombre')->paginate(20);

        return view('proveedores.index', compact('proveedores'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $proveedor = Proveedor::create($this->validateProveedor($request));

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $proveedor->id,
                'nombre' => $proveedor->nombre,
                'contacto' => $proveedor->contacto,
                'message' => 'Proveedor creado con éxito.',
            ], 201);
        }

        return redirect()->route('proveedores.index')->with('success', 'Proveedor creado con éxito.');
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($this->validateProveedor($request, $proveedor));

        return redirect()->route('proveedores.index')->with('success', 'Proveedor actualizado con éxito.');
    }

    private function validateProveedor(Request $request, ?Proveedor $proveedor = null): array
    {
        $telefono = $request->input('telefono');
        if (is_string($telefono)) {
            $request->merge(['telefono' => TelefonoChileno::normalizar($telefono)]);
        }
        $request->merge([
            'nombre' => TextoPersona::normalizar($request->input('nombre')),
            'contacto' => TextoPersona::normalizar($request->input('contacto')),
        ]);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('proveedores', 'nombre')->ignore($proveedor?->id)],
            'contacto' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'regex:/^\+56[2-9][0-9]{8}$/'],
            'correo' => ['required', 'string', 'email', new CorreoConDominio, 'max:255'],
            'pagina_web' => ['nullable', 'string', 'url:http,https', 'max:255'],
        ], [
            'nombre.required' => 'El nombre del proveedor es obligatorio.',
            'nombre.unique' => 'Ya existe un proveedor con ese nombre.',
            'contacto.required' => 'El contacto es obligatorio.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.regex' => 'El teléfono debe tener formato chileno, por ejemplo +56912345678.',
            'correo.required' => 'El correo electrónico es obligatorio.',
            'correo.email' => 'Ingresa un correo electrónico válido.',
            'pagina_web.url' => 'Ingresa una página web válida con http:// o https://.',
        ]);
    }
}
