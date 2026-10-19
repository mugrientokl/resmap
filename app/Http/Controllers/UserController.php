<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $usuarios = User::orderBy('name')->get();

        return $request->expectsJson()
            ? response()->json($usuarios)
            : view('usuarios.index', compact('usuarios'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', 'max:255', Password::min(12)],
            'rol' => 'required|in:Administrador,Vendedor',
        ]);

        $user = User::create($validated);

        if (! $request->expectsJson()) {
            return redirect()->route('usuarios.index')->with('success', 'Usuario creado con éxito.');
        }

        return response()->json([
            'message' => 'Usuario creado con éxito.',
            'user' => $user,
        ], 201);
    }
}
