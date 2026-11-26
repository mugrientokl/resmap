<div>
    <!-- Simplicity is the consequence of refined emotions. - Jean D'Alembert -->
</div>
@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-xl rounded-lg bg-white p-6 shadow-sm" role="dialog" aria-modal="true" aria-labelledby="initial-password-title">
    <h1 id="initial-password-title" class="text-2xl font-black">Cuenta creada</h1>
    <p class="mt-3 text-gray-700">Credenciales iniciales de {{ $user->name }}. Cópialas y entrégalas por un medio seguro. La contraseña no volverá a mostrarse.</p>
    <label for="initial-username" class="mt-5 block text-sm font-bold">Nombre de usuario</label>
    <input id="initial-username" type="text" value="{{ $user->username }}" readonly class="mt-2 w-full rounded-md border p-3">
    <label for="initial-password" class="mt-5 block text-sm font-bold">Contraseña inicial</label>
    <input id="initial-password" type="text" value="{{ $contrasenaInicial }}" readonly class="mt-2 w-full rounded-md border p-3">
    <div class="mt-5 flex flex-wrap gap-3">
        <button id="copy-initial-credentials" type="button" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Copiar credenciales</button>
        <button id="copy-initial-password" type="button" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Copiar contraseña</button>
        <a id="close-initial-password" href="{{ route('usuarios.index') }}" class="rounded-md border px-4 py-2 font-bold">Cerrar</a>
    </div>
    <p id="copy-status" class="mt-3 text-sm text-gray-700" role="status" aria-live="polite"></p>
</div>
<script>
    const passwordField = document.getElementById('initial-password');
    const usernameField = document.getElementById('initial-username');
    const copyStatus = document.getElementById('copy-status');
    document.getElementById('copy-initial-credentials').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(`Usuario: ${usernameField.value}\nContraseña: ${passwordField.value}`);
            copyStatus.textContent = 'Credenciales copiadas.';
        } catch (error) {
            usernameField.select();
            copyStatus.textContent = 'No se pudo copiar automáticamente. Selecciona y copia los datos.';
        }
    });
    document.getElementById('copy-initial-password').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(passwordField.value);
            copyStatus.textContent = 'Contraseña copiada.';
        } catch (error) {
            passwordField.select();
            copyStatus.textContent = 'No se pudo copiar automáticamente. Copia el texto seleccionado.';
        }
    });
    document.getElementById('close-initial-password').addEventListener('click', () => {
        passwordField.value = '';
        passwordField.removeAttribute('value');
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            passwordField.value = '';
            passwordField.removeAttribute('value');
            window.location.replace(@json(route('usuarios.index')));
        }
    });
</script>
@endsection
