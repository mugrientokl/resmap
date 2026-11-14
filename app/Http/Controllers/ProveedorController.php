<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
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

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:proveedores,nombre'],
            'contacto' => ['required', 'string', 'max:255'],
        ]);

        Proveedor::create($datos);

        return redirect()->route('proveedores.index')->with('success', 'Proveedor creado con éxito.');
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('proveedores', 'nombre')->ignore($proveedor->id)],
            'contacto' => ['required', 'string', 'max:255'],
        ]);

        $proveedor->update($datos);

        return redirect()->route('proveedores.index')->with('success', 'Proveedor actualizado con éxito.');
    }
}
