@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="mx-auto max-w-4xl">
    <header class="border-b border-[#e8c8c3] pb-5">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-[#9f2f25]">Cuenta RESMAP</p>
        <h1 class="mt-2 text-3xl font-black text-[#241817]">Mi perfil</h1>
        <p class="mt-2 text-gray-600">{{ auth()->user()->name }} · {{ auth()->user()->rol }}</p>
    </header>

    <section class="mt-7 rounded-xl border border-[#e8c8c3] bg-white p-5 shadow-sm sm:p-7" aria-labelledby="name-settings-title">
        <h2 id="name-settings-title" class="text-xl font-black">Cambiar nombre de usuario</h2>
        <form method="POST" action="{{ route('perfil.nombre.update') }}" class="mt-5 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="profile-name" class="block text-sm font-bold">Nombre visible</label>
                <input id="profile-name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="255" class="mt-2 w-full max-w-xl border border-gray-300 p-3">
                @error('name')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="profile-username" class="block text-sm font-bold">Nombre de usuario</label>
                <input id="profile-username" type="text" name="username" value="{{ old('username', auth()->user()->username) }}" required maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" class="mt-2 w-full max-w-xl border border-gray-300 p-3">
                @error('username')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="name-current-password" class="block text-sm font-bold">Contraseña actual</label>
                <input id="name-current-password" type="password" name="current_password" required autocomplete="current-password" class="mt-2 w-full max-w-xl border border-gray-300 p-3">
                @error('current_password')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="bg-[#b52f25] px-5 py-3 font-bold text-white hover:bg-[#8f241d]">Guardar nombre</button>
        </form>
    </section>

    <section class="mt-7 rounded-xl border border-[#e8c8c3] bg-white p-5 shadow-sm sm:p-7" aria-labelledby="password-settings-title">
        <h2 id="password-settings-title" class="text-xl font-black">Cambiar contraseña</h2>
        <form method="POST" action="{{ route('perfil.contrasena.update') }}" class="mt-5 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="current-password" class="block text-sm font-bold">Contraseña actual</label>
                <input id="current-password" type="password" name="current_password" required autocomplete="current-password" class="mt-2 w-full max-w-xl border border-gray-300 p-3">
                @error('current_password')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-password" class="block text-sm font-bold">Nueva contraseña</label>
                <input id="new-password" type="password" name="password" required minlength="12" maxlength="255" autocomplete="new-password" class="mt-2 w-full max-w-xl border border-gray-300 p-3">
                <p class="mt-1 text-sm text-gray-500">Usa al menos 12 caracteres.</p>
                @error('password')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-password-confirmation" class="block text-sm font-bold">Repetir contraseña</label>
                <input id="new-password-confirmation" type="password" name="password_confirmation" required minlength="12" maxlength="255" autocomplete="new-password" class="mt-2 w-full max-w-xl border border-gray-300 p-3">
            </div>
            <button type="submit" class="bg-[#b52f25] px-5 py-3 font-bold text-white hover:bg-[#8f241d]">Cambiar contraseña</button>
        </form>
    </section>

</div>
@endsection