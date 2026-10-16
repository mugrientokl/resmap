<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('nombre_categoria')->paginate(20)->withQueryString();

        return view('categorias.index', compact('categorias'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_categoria' => [
                'required',
                'string',
                'max:255',
                'unique:categorias,nombre_categoria',
                'not_regex:/^\d+$/',
            ],
            'descripcion' => 'nullable|string',
        ], [
            'nombre_categoria.not_regex' => 'El nombre de la categoría no puede contener únicamente números; debe incluir al menos una letra.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('validation_error', $validator->errors()->first());
        }

        Categoria::create($request->only(['nombre_categoria', 'descripcion']));

        return redirect()->route('categorias.index')->with('success', 'Categoría creada con éxito.');
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre_categoria' => [
                'required',
                'string',
                'max:255',
                'unique:categorias,nombre_categoria,'.$id.',id_categoria',
                'not_regex:/^\d+$/',
            ],
            'descripcion' => 'nullable|string',
        ], [
            'nombre_categoria.not_regex' => 'El nombre de la categoría no puede contener únicamente números; debe incluir al menos una letra.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('validation_error', $validator->errors()->first());
        }

        $categoria->update($request->only(['nombre_categoria', 'descripcion']));

        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada con éxito.');
    }

    public function destroy($id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return redirect()->route('categorias.index')->with('success', 'Categoría eliminada con éxito.');
    }
}
