@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Administración</p>
        <h1 class="mt-1 text-3xl font-black text-gray-900">Usuarios y roles</h1>
        <p class="mt-2 text-gray-500">Cada persona usa su propia cuenta. Al crearla se mostrará una contraseña inicial una sola vez para entregarla de forma segura.</p>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @unless($archivados)
        <div class="flex justify-end">
            <button type="button" id="toggle-create-user" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="create-user-panel" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#b52f25] px-5 py-3 font-bold text-white shadow-sm hover:bg-[#8f241d]">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                Crear usuario
            </button>
        </div>
        <section id="create-user-panel" class="rounded-xl border border-[#ead8d5] bg-white p-5 shadow-sm sm:p-6" @unless($errors->any()) hidden @endunless>
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#f7e8e6] text-[#9f2f25]" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19a6 6 0 0 0-12 0m6-9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10-2v6m-3-3h6"/></svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-black text-gray-900">Crear usuario</h2>
                        <p class="mt-1 text-sm text-gray-500">Completa los datos de la cuenta y configura sus accesos.</p>
                    </div>
                </div>
                <button type="button" id="close-create-user" class="text-2xl leading-none text-gray-500 hover:text-gray-800" aria-label="Cerrar formulario">&times;</button>
            </div>
            <form id="create-user-form" method="POST" action="{{ route('usuarios.store') }}" class="mt-6 grid gap-4 md:grid-cols-2" data-confirm-message="¿Confirmas crear esta cuenta de usuario con los datos ingresados?" data-confirm-title="Crear usuario" data-confirm-text="Crear cuenta">
                @csrf
                <label for="nuevo-username" class="block text-sm font-bold text-gray-700">Usuario
                    <input id="nuevo-username" name="username" value="{{ old('username') }}" required placeholder="Ej. vendedor1" class="mt-2 w-full rounded-lg border border-gray-300 p-3 font-normal focus:border-[#b52f25] focus:outline-none focus:ring-2 focus:ring-[#f7e8e6]">
                </label>
                <label for="nuevo-name" class="block text-sm font-bold text-gray-700">Nombre completo
                    <input id="nuevo-name" name="name" value="{{ old('name') }}" required placeholder="Nombre y apellido" class="mt-2 w-full rounded-lg border border-gray-300 p-3 font-normal focus:border-[#b52f25] focus:outline-none focus:ring-2 focus:ring-[#f7e8e6]">
                </label>
                <label for="nuevo-email" class="block text-sm font-bold text-gray-700">Correo electrónico
                    <input id="nuevo-email" type="email" name="email" value="{{ old('email') }}" required pattern="{{ \App\Rules\CorreoConDominio::PATRON }}" title="Ingresa un correo con dominio completo, por ejemplo nombre@ejemplo.cl" placeholder="correo@ejemplo.com" class="mt-2 w-full rounded-lg border border-gray-300 p-3 font-normal focus:border-[#b52f25] focus:outline-none focus:ring-2 focus:ring-[#f7e8e6]">
                </label>
                <label for="nuevo-rol" class="block text-sm font-bold text-gray-700">Rol
                    <select id="nuevo-rol" name="rol" required class="mt-2 w-full rounded-lg border border-gray-300 p-3 font-normal focus:border-[#b52f25] focus:outline-none focus:ring-2 focus:ring-[#f7e8e6]">
                        <option value="Vendedor" @selected(old('rol', 'Vendedor') === 'Vendedor')>Vendedor</option>
                        <option value="Administrador" @selected(old('rol') === 'Administrador')>Administrador</option>
                    </select>
                    <span class="mt-1 block text-xs font-normal text-gray-500">Los administradores tienen acceso completo.</span>
                </label>
                <input type="hidden" name="permisos_configurados" value="1">
                @php($permisosIniciales = old('permisos_configurados') ? old('permisos', []) : config('permisos.vendedor_actual'))
                @php($permisosIniciales = is_array($permisosIniciales) ? $permisosIniciales : [])
                <details id="nuevo-permisos-panel" data-permissions class="group rounded-xl border border-[#ead8d5] bg-[#fffaf9] md:col-span-2" @if(old('permisos_configurados') && old('rol', 'Vendedor') === 'Vendedor') open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 rounded-xl p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25]">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#f7e8e6] text-[#9f2f25]" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v5c0 5 3.4 8.3 8 10 4.6-1.7 8-5 8-10V6l-8-3Zm-3 9 2 2 4-4"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-black text-gray-900">Permisos del vendedor</span>
                            <span class="block text-sm text-gray-500">Personaliza el acceso a módulos y funciones</span>
                        </span>
                        <span data-permissions-count aria-live="polite" class="rounded-full bg-white px-3 py-1 text-xs font-bold text-[#9f2f25]">{{ count($permisosIniciales) }} {{ count($permisosIniciales) === 1 ? 'seleccionado' : 'seleccionados' }}</span>
                        <svg class="h-5 w-5 text-[#9f2f25] transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="border-t border-[#ead8d5] p-4 sm:p-5">
                        @include('usuarios.permisos', ['seleccionados' => $permisosIniciales])
                    </div>
                </details>
                <div class="md:col-span-2">
                    <button class="rounded-lg bg-[#b52f25] px-6 py-3 font-bold text-white shadow-sm transition hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25]">Crear cuenta</button>
                </div>
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
                                    @php($permisosUsuario = array_values(array_intersect(array_keys(config('permisos.opciones')), $usuario->permisos ?? config('permisos.vendedor_actual'))))
                                    <details data-permissions class="w-full rounded-lg border border-[#ead8d5] bg-[#fffaf9]">
                                        <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2 font-bold text-[#9f2f25]">
                                            <span class="flex-1">Configurar permisos</span>
                                            <span data-permissions-count aria-live="polite" class="rounded-full bg-white px-2 py-1 text-xs">{{ count($permisosUsuario) }} {{ count($permisosUsuario) === 1 ? 'seleccionado' : 'seleccionados' }}</span>
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                        </summary>
                                        <form method="POST" action="{{ route('usuarios.permisos.update', $usuario) }}" class="space-y-4 border-t border-[#ead8d5] p-4">
                                            @csrf
                                            @method('PUT')
                                            @include('usuarios.permisos', ['seleccionados' => $permisosUsuario])
                                            <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Guardar permisos</button>
                                        </form>
                                    </details>
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
<script>
    const toggleCreateUser = document.getElementById('toggle-create-user');
    const closeCreateUser = document.getElementById('close-create-user');
    const createUserPanel = document.getElementById('create-user-panel');
    const setCreateUserPanel = (open) => {
        if (!createUserPanel || !toggleCreateUser) return;
        createUserPanel.hidden = !open;
        toggleCreateUser.setAttribute('aria-expanded', String(open));
        if (open) document.getElementById('nuevo-username')?.focus();
    };
    toggleCreateUser?.addEventListener('click', () => setCreateUserPanel(createUserPanel.hidden));
    closeCreateUser?.addEventListener('click', () => setCreateUserPanel(false));

    const nuevoRol = document.getElementById('nuevo-rol');
    const nuevoPermisos = document.getElementById('nuevo-permisos-panel');
    const actualizarPermisosNuevoUsuario = () => {
        if (!nuevoRol || !nuevoPermisos) return;
        nuevoPermisos.hidden = nuevoRol.value !== 'Vendedor';
        if (nuevoRol.value !== 'Vendedor') nuevoPermisos.removeAttribute('open');
    };
    nuevoRol?.addEventListener('change', actualizarPermisosNuevoUsuario);
    actualizarPermisosNuevoUsuario();
    document.querySelectorAll('[data-permissions]').forEach((panel) => {
        const checkboxes = panel.querySelectorAll('input[name="permisos[]"]');
        const counter = panel.querySelector('[data-permissions-count]');
        const updateCount = () => {
            const count = Array.from(checkboxes).filter((checkbox) => checkbox.checked).length;
            counter.textContent = `${count} seleccionado${count === 1 ? '' : 's'}`;
        };
        panel.addEventListener('change', updateCount);
        [
            ['[data-permissions-select]', true],
            ['[data-permissions-clear]', false],
        ].forEach(([selector, checked]) => {
            const button = panel.querySelector(selector);
            button.hidden = false;
            button.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => { checkbox.checked = checked; });
                updateCount();
            });
        });
    });
</script>
@endsection