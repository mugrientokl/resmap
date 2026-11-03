@extends('layouts.app')

@section('content')
<div class="bg-white shadow-md rounded-lg overflow-hidden p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            Listado de Clientes y Constructoras
        </h2>
    </div>

    <form
        method="GET"
        action="{{ route('clientes.index') }}"
        class="mb-6 flex flex-wrap gap-2"
    >
        <div id="clientes-buscador" class="relative min-w-64 flex-1">
            <input
                type="search"
                id="clientes-buscar"
                name="buscar"
                value="{{ request('buscar') }}"
                placeholder="Buscar por RUT, nombre o correo"
                aria-label="Buscar clientes"
                autocomplete="off"
                aria-controls="clientes-sugerencias"
                aria-expanded="false"
                class="w-full rounded-md border border-gray-300 p-2 shadow-sm"
            >
            <div
                id="clientes-sugerencias"
                class="absolute left-0 top-full z-40 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg"
                role="listbox"
            ></div>
            <p id="clientes-sugerencias-error" class="sr-only" role="status" aria-live="polite"></p>
        </div>

        <button
            type="submit"
            class="rounded-md bg-[#b52f25] px-4 py-2 font-medium text-white hover:bg-[#8f241d]"
        >
            Filtrar
        </button>

        <a
            href="{{ route('clientes.index') }}"
            class="rounded-md border border-[#d9aaa3] px-4 py-2 font-medium text-[#9f2f25] hover:bg-[#f7e8e6]"
        >
            Limpiar
        </a>
    </form>

    <div class="mx-auto max-w-7xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-center">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">
                        RUT
                    </th>

                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">
                        Razón Social / Nombre
                    </th>

                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">
                        Correo
                    </th>

                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">
                        Teléfono
                    </th>

                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">
                        Dirección
                    </th>

                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">
                        Acciones
                    </th>
                </tr>
            </thead>

            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($clientes as $cliente)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono font-bold text-gray-700">
                            {{ $cliente->rut }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <span class="font-bold text-gray-800">
                                {{ $cliente->nombre }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $cliente->correo ?? 'N/A' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $cliente->telefono ?? 'N/A' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $cliente->direccion ?? 'N/A' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <div class="flex items-center justify-center gap-2">
                                <a
                                    href="{{ route('clientes.historial', $cliente->id_cliente) }}"
                                    class="inline-flex rounded-md bg-[#b52f25] px-3 py-2 text-white hover:bg-[#8f241d]"
                                >
                                    Ver historial
                                </a>

                                @if(auth()->user()->rol === 'Administrador')
                                    <a
                                        href="{{ route('clientes.edit', $cliente->id_cliente) }}"
                                        title="Editar cliente"
                                        aria-label="Editar cliente"
                                        class="inline-flex rounded-md p-2 text-[#9f2f25] hover:bg-[#f7e8e6] hover:text-[#721d18]"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="m16.862 3.487 3.651 3.651M4 20h4l10.862-10.862a2.587 2.587 0 0 0-3.651-3.651L4.349 16.349A2 2 0 0 0 4 17.763V20Z"
                                            />
                                        </svg>
                                    </a>

                                    <form
                                        action="{{ route('clientes.destroy', $cliente->id_cliente) }}"
                                        method="POST"
                                        class="inline-block"
                                        data-confirm-message="¿Seguro que deseas eliminar este cliente? Esta acción no se puede deshacer."
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            title="Eliminar cliente"
                                            aria-label="Eliminar cliente"
                                            class="inline-flex rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-900"
                                        >
                                            <svg
                                                class="h-5 w-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="m6 6 12 12M18 6 6 18"
                                                />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-sm text-gray-500">
                            No se encontraron clientes.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $clientes->links() }}
    </div>
</div>

<script>
(() => {
    const buscador = document.getElementById('clientes-buscar');
    const contenedor = document.getElementById('clientes-buscador');
    const lista = document.getElementById('clientes-sugerencias');
    const estado = document.getElementById('clientes-sugerencias-error');
    let temporizador;
    let controlador;

    function cerrarSugerencias() {
        lista.replaceChildren();
        lista.classList.add('hidden');
        buscador.setAttribute('aria-expanded', 'false');
    }

    async function buscarSugerencias() {
        const termino = buscador.value.trim();

        if (termino.length < 2) {
            cerrarSugerencias();
            estado.textContent = '';
            return;
        }

        controlador?.abort();
        controlador = new AbortController();
        estado.textContent = '';

        try {
            const url = new URL(@json(route('clientes.sugerencias')), window.location.origin);
            url.searchParams.set('buscar', termino);

            const respuesta = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: controlador.signal
            });

            if (!respuesta.ok) {
                throw new Error('No fue posible obtener las sugerencias.');
            }

            const clientes = await respuesta.json();
            lista.replaceChildren();

            clientes.forEach(cliente => {
                const opcion = document.createElement('button');
                opcion.type = 'button';
                opcion.setAttribute('role', 'option');
                opcion.className =
                    'block w-full border-b border-gray-100 px-4 py-3 text-left hover:bg-red-50 focus:bg-red-50';

                const nombre = document.createElement('span');
                nombre.className = 'block text-sm font-semibold text-gray-900';
                nombre.textContent = cliente.nombre;

                const identificadores = document.createElement('span');
                identificadores.className = 'block text-xs text-gray-600';
                identificadores.textContent =
                    `${cliente.rut}${cliente.correo ? ` · ${cliente.correo}` : ''}`;

                opcion.append(nombre, identificadores);
                opcion.addEventListener('click', () => {
                    buscador.value = cliente.rut;
                    cerrarSugerencias();
                    buscador.form.requestSubmit();
                });
                lista.append(opcion);
            });

            if (clientes.length === 0) {
                const mensaje = document.createElement('p');
                mensaje.className = 'px-4 py-3 text-sm text-gray-500';
                mensaje.textContent = 'No se encontraron clientes.';
                lista.append(mensaje);
            }

            lista.classList.remove('hidden');
            buscador.setAttribute('aria-expanded', 'true');
        } catch (error) {
            if (error.name !== 'AbortError') {
                cerrarSugerencias();
                estado.textContent = 'No se pudieron cargar las sugerencias. Puedes usar Filtrar.';
            }
        }
    }

    buscador.addEventListener('input', () => {
        window.clearTimeout(temporizador);
        temporizador = window.setTimeout(buscarSugerencias, 200);
    });

    buscador.addEventListener('focus', buscarSugerencias);

    buscador.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            cerrarSugerencias();
        } else if (event.key === 'ArrowDown') {
            const primeraOpcion = lista.querySelector('button');

            if (primeraOpcion) {
                event.preventDefault();
                primeraOpcion.focus();
            }
        }
    });

    lista.addEventListener('keydown', event => {
        const opciones = Array.from(lista.querySelectorAll('button'));
        const indice = opciones.indexOf(document.activeElement);

        if (indice === -1) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            opciones[(indice + 1) % opciones.length].focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();

            if (indice === 0) {
                buscador.focus();
            } else {
                opciones[indice - 1].focus();
            }
        } else if (event.key === 'Escape') {
            cerrarSugerencias();
            buscador.focus();
        }
    });

    document.addEventListener('click', event => {
        if (!contenedor.contains(event.target)) {
            cerrarSugerencias();
        }
    });
})();
</script>
@endsection