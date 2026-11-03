@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Administración</p>
        <h1 class="mt-1 text-3xl font-black text-gray-900">Usuarios y roles</h1>
        <p class="mt-2 text-gray-500">Cada persona usa su propia cuenta. Nunca compartas contraseñas: enviaremos un enlace al correo para que cada usuario cree la suya.</p>
    </div>

    @if(session('success'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-700" role="status">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800" role="alert">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @unless($archivados)
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black">Crear usuario</h2>
            <form method="POST" action="{{ route('usuarios.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <input name="username" value="{{ old('username') }}" required placeholder="Usuario" class="rounded-md border p-2">
                <input name="name" value="{{ old('name') }}" required placeholder="Nombre completo" class="rounded-md border p-2">
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="Correo electrónico" class="rounded-md border p-2">
                <select name="rol" required class="rounded-md border p-2">
                    <option value="Vendedor" @selected(old('rol', 'Vendedor') === 'Vendedor')>Vendedor</option>
                    <option value="Administrador" @selected(old('rol') === 'Administrador')>Administrador</option>
                </select>
                <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white hover:bg-[#8f241d]">Crear cuenta y enviar enlace</button>
            </form>
        </section>
    @endunless

    <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 p-6">
            <h2 class="text-lg font-black">{{ $archivados ? 'Cuentas archivadas' : 'Usuarios registrados' }}</h2>
            <a href="{{ route('usuarios.index', $archivados ? [] : ['archivados' => 1]) }}" class="font-bold text-[#9f2f25] underline">
                {{ $archivados ? 'Ver cuentas activas e inhabilitadas' : 'Ver cuentas archivadas' }}
            </a>
        </div>
        <table class="min-w-full text-left text-sm">
            <thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]">
                <tr>
                    <th class="px-6 py-3">Nombre</th>
                    <th class="px-6 py-3">Usuario</th>
                    <th class="px-6 py-3">Correo</th>
                    <th class="px-6 py-3">Rol</th>
                    <th class="px-6 py-3">Estado</th>
                    <th class="px-6 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($usuarios as $usuario)
                    <tr>
                        <td class="px-6 py-3">{{ $usuario->name }}</td>
                        <td class="px-6 py-3">{{ $usuario->username }}</td>
                        <td class="px-6 py-3">{{ $usuario->email }}</td>
                        <td class="px-6 py-3 font-bold">{{ $usuario->rol }}</td>
                        <td class="px-6 py-3">
                            <span class="{{ $usuario->trashed() ? 'text-gray-700' : ($usuario->activo ? 'text-green-700' : 'text-red-700') }}">
                                {{ $usuario->trashed() ? 'Archivada' : ($usuario->activo ? 'Activa' : 'Inhabilitada') }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            @if($archivados)
                                <form method="POST" action="{{ route('usuarios.restaurar', $usuario->id) }}" data-confirm-message="La cuenta volverá a estar habilitada y podrá iniciar sesión. ¿Deseas continuar?" data-confirm-title="Restaurar cuenta" data-confirm-text="Restaurar cuenta">
                                    @csrf
                                    <button class="rounded border border-green-300 px-3 py-1 font-bold text-green-700">Restaurar cuenta</button>
                                </form>
                            @elseif($usuario->rol === 'Vendedor')
                                <div class="flex flex-wrap gap-2">
                                    @if($usuario->activo)
                                        <form method="POST" action="{{ route('usuarios.enlace-contrasena', $usuario) }}">
                                            @csrf
                                            <button class="rounded border border-[#b52f25] px-3 py-1 font-bold text-[#9f2f25]">Enviar enlace de contraseña</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('usuarios.estado', $usuario) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="activo" value="{{ $usuario->activo ? 0 : 1 }}">
                                        <button class="rounded border px-3 py-1 font-bold {{ $usuario->activo ? 'border-red-300 text-red-700' : 'border-green-300 text-green-700' }}">
                                            {{ $usuario->activo ? 'Inhabilitar' : 'Reactivar' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" data-confirm-message="Se archivará la cuenta, se revocará su acceso y se conservarán las ventas y la auditoría asociadas. Podrás restaurarla después. ¿Continuar?" data-confirm-title="Archivar cuenta de vendedor" data-confirm-text="Archivar cuenta">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded border border-gray-400 px-3 py-1 font-bold text-gray-700">Archivar cuenta</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-gray-500">Cuenta administrativa</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
@endsection