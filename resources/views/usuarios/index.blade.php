@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Administración</p>
        <h1 class="mt-1 text-3xl font-black text-gray-900">Usuarios y roles</h1>
        <p class="mt-2 text-gray-500">Crea cuentas con permisos diferenciados para la operación diaria.</p>
    </div>

    @if(session('success'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-700">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-lg bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black">Crear usuario</h2>
        <form method="POST" action="{{ route('usuarios.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
            @csrf
            <input name="username" value="{{ old('username') }}" required placeholder="Usuario" class="rounded-md border p-2">
            <input name="name" value="{{ old('name') }}" required placeholder="Nombre completo" class="rounded-md border p-2">
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="Correo electrónico" class="rounded-md border p-2">
            <input type="password" name="password" required minlength="8" placeholder="Contraseña (mínimo 8 caracteres)" class="rounded-md border p-2">
            <select name="rol" required class="rounded-md border p-2">
                <option value="Vendedor" @selected(old('rol', 'Vendedor') === 'Vendedor')>Vendedor</option>
                <option value="Administrador" @selected(old('rol') === 'Administrador')>Administrador</option>
            </select>
            <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white hover:bg-[#8f241d]">Crear usuario</button>
        </form>
    </section>

    <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <h2 class="p-6 text-lg font-black">Usuarios registrados</h2>
        <table class="min-w-full text-left text-sm"><thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]"><tr><th class="px-6 py-3">Nombre</th><th class="px-6 py-3">Usuario</th><th class="px-6 py-3">Correo</th><th class="px-6 py-3">Rol</th></tr></thead><tbody class="divide-y">@foreach($usuarios as $usuario)<tr><td class="px-6 py-3">{{ $usuario->name }}</td><td class="px-6 py-3">{{ $usuario->username }}</td><td class="px-6 py-3">{{ $usuario->email }}</td><td class="px-6 py-3 font-bold">{{ $usuario->rol }}</td></tr>@endforeach</tbody></table>
    </section>
</div>
@endsection