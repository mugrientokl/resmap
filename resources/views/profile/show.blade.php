@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="mx-auto max-w-4xl">
    <header class="border-b border-[#e8c8c3] pb-5">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-[#9f2f25]">Cuenta RESMAP</p>
        <h1 class="mt-2 text-3xl font-black text-[#241817]">Mi perfil</h1>
        <p class="mt-2 text-gray-600">{{ auth()->user()->name }} · {{ auth()->user()->rol }}</p>
    </header>

    <section class="mt-7 border border-[#e8c8c3] bg-white p-5 shadow-sm sm:p-7" aria-labelledby="name-settings-title">
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

    <section class="mt-7 border border-[#e8c8c3] bg-white p-5 shadow-sm sm:p-7" aria-labelledby="password-settings-title">
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

    <section class="mt-7 border border-[#e8c8c3] bg-white p-5 shadow-sm sm:p-7" aria-labelledby="face-settings-title">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#f0ddda] pb-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#9f2f25]">Acceso adicional</p>
                <h2 id="face-settings-title" class="mt-2 text-xl font-black">Reconocimiento facial</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">El rostro identifica tu cuenta en el servidor. Las imágenes de cámara se procesan en tu dispositivo y no se guardan.</p>
            </div>
            <span class="border px-3 py-1 text-sm font-bold {{ $faceRegistered ? 'border-green-300 bg-green-50 text-green-800' : 'border-gray-300 bg-gray-50 text-gray-600' }}">
                {{ $faceRegistered ? 'Rostro registrado' : 'Sin registrar' }}
            </span>
        </div>

        @if($faceConsentAt)
            <p class="mt-4 text-sm text-gray-600">Consentimiento registrado el {{ $faceConsentAt->format('d/m/Y H:i') }}.</p>
        @endif

        <form id="face-profile-form" method="POST" action="{{ route('perfil.rostro.store') }}" class="mt-6 space-y-5" data-face-mode="profile" data-models-url="{{ asset('models') }}">
            @csrf
            <div>
                <label for="face-enroll-password" class="block text-sm font-bold">Confirma tu contraseña</label>
                <input id="face-enroll-password" type="password" name="password" required autocomplete="current-password" class="mt-2 w-full max-w-xl border border-gray-300 p-3 outline-none focus:border-[#b52f25]">
                @error('password')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <label class="flex max-w-3xl items-start gap-3 text-sm leading-6 text-gray-700">
                <input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 accent-[#b52f25]">
                <span>Autorizo guardar cifrado el descriptor facial de esta cuenta para compararlo al iniciar sesión. Entiendo que las imágenes de cámara no se guardan y que puedo eliminar este registro desde mi perfil.</span>
            </label>
            @error('consent')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            <div class="flex flex-wrap items-center gap-4">
                <button type="button" data-face-open class="inline-flex min-h-11 items-center gap-2 bg-[#b52f25] px-5 py-3 font-bold text-white transition hover:bg-[#8f241d] focus:outline-none focus:ring-2 focus:ring-[#b52f25] focus:ring-offset-2">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7.5h3l1.5-2h7L17 7.5h3v11H4z"/><circle cx="12" cy="13" r="3.5" stroke-width="1.8"/></svg>
                    {{ $faceRegistered ? 'Actualizar rostro' : 'Registrar rostro' }}
                </button>
                <p data-face-status class="text-sm text-gray-600" role="status" aria-live="polite"></p>
            </div>
        </form>

        @if($faceRegistered)
            <form method="POST" action="{{ route('perfil.rostro.destroy') }}" class="mt-7 border-t border-[#f0ddda] pt-6">
                @csrf
                @method('DELETE')
                <label for="face-delete-password" class="block text-sm font-bold">Contraseña para eliminar el registro facial</label>
                <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <input id="face-delete-password" type="password" name="password" required autocomplete="current-password" class="w-full max-w-xl border border-gray-300 p-3 outline-none focus:border-[#b52f25]">
                    <button type="submit" class="min-h-11 border border-red-300 px-4 py-3 font-bold text-red-800 transition hover:bg-red-50">Eliminar rostro</button>
                </div>
            </form>
        @endif
    </section>
</div>
@include('components.face-camera')
@vite('resources/js/face-recognition.js')
@endsection