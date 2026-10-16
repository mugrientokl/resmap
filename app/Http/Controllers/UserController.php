<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::orderBy('name')->get();

        return $request->expectsJson()
            ? response()->json($usuarios)
            : view('usuarios.index', compact('usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'rol' => 'required|in:Administrador,Vendedor',
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => $request->rol,
        ]);

        if (! $request->expectsJson()) {
            return redirect()->route('usuarios.index')->with('success', 'Usuario creado con éxito.');
        }

        return response()->json([
            'message' => 'Usuario creado con éxito.',
            'user' => $user,
        ], 201);
    }
}
