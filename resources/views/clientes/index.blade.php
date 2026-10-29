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
        <input
            type="search"
            name="buscar"
            value="{{ request('buscar') }}"
            placeholder="Buscar por RUT, nombre o correo"
            aria-label="Buscar clientes"
            class="min-w-64 flex-1 rounded-md border border-gray-300 p-2 shadow-sm"
        >

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

    @if(session('success'))
        <div class="mb-4 rounded border border-green-400 bg-green-100 px-4 py-3 text-green-700">
            {{ session('success') }}
        </div>
    @endif

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
                            <a
                                href="{{ route('clientes.historial', $cliente->id_cliente) }}"
                                class="font-bold text-[#9f2f25] hover:underline"
                            >
                                {{ $cliente->nombre }}
                            </a>
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
@endsection