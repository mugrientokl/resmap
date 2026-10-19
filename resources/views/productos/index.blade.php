@extends('layouts.app')

@section('content')

<div class="bg-white shadow-md rounded-lg overflow-hidden p-6">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            Inventario de Repuestos y Maquinaria
        </h2>

        <div class="flex flex-wrap gap-2">

            @if(auth()->user()->rol === 'Administrador')

                <a href="{{ route('inventario.movimientos') }}"
                   class="rounded-md border border-[#b52f25] px-4 py-2 font-medium text-[#9f2f25] hover:bg-[#f7e8e6]">
                    Movimientos de Inventario
                </a>

                <a href="{{ route('productos.create') }}"
                   class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d] font-medium">
                    + Nuevo Repuesto
                </a>

            @endif

            <a href="{{ route('productos.exportar.excel', request()->query()) }}"
               class="rounded-md border border-[#b52f25] px-4 py-2 font-medium text-[#9f2f25] hover:bg-[#f7e8e6]">
                Excel
            </a>

            <a href="{{ route('productos.exportar.pdf', request()->query()) }}"
               target="_blank"
               class="rounded-md bg-[#b52f25] px-4 py-2 font-medium text-white hover:bg-[#8f241d]">
                PDF
            </a>

        </div>
    </div>

    @if(session('success'))

        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('success') }}
        </div>

    @endif

    <form method="GET"
          action="{{ route('productos.index') }}"
          class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-3">

        <input
            type="search"
            name="nombre"
            value="{{ request('nombre') }}"
            placeholder="Buscar por nombre o código"
            class="border-gray-300 rounded-md shadow-sm border p-2"
        >

        <select
            name="categoria"
            class="border-gray-300 rounded-md shadow-sm border p-2"
        >

            <option value="">Todas las categorías</option>

            @foreach($categorias as $categoria)

                <option
                    value="{{ $categoria->id_categoria }}"
                    @selected(request('categoria') == $categoria->id_categoria)
                >
                    {{ $categoria->nombre_categoria }}
                </option>

            @endforeach

        </select>

        <select
            name="stock"
            class="border-gray-300 rounded-md shadow-sm border p-2"
        >

            <option value="">Todo el stock</option>

            <option
                value="disponible"
                @selected(request('stock') === 'disponible')
            >
                Con stock
            </option>

            <option
                value="critico"
                @selected(request('stock') === 'critico')
            >
                Stock crítico
            </option>

            <option
                value="agotado"
                @selected(request('stock') === 'agotado')
            >
                Sin stock
            </option>

        </select>

        <div class="flex gap-2">

            <button
                type="submit"
                class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d]"
            >
                Filtrar
            </button>

            <a
                href="{{ route('productos.index') }}"
                class="border border-[#d9aaa3] text-[#9f2f25] px-4 py-2 rounded-md hover:bg-[#f7e8e6]"
            >
                Limpiar
            </a>

        </div>

    </form>

    <div class="mx-auto max-w-6xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-center">
            <thead class="bg-gray-50">

                <tr>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Código</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Categoría</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Unidad</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Estado catálogo</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Precio</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Stock</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                </tr>

            </thead>

            <tbody class="bg-white divide-y divide-gray-200">

                @foreach($productos as $producto)

                    <tr class="transition-colors hover:bg-gray-50">

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600">
                            {{ $producto->codigo_origen ?: $producto->codigo_barra }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $producto->nombre }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $producto->categoria->nombre_categoria ?? 'Sin Categoría' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ ucfirst($producto->unidad) }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $producto->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            $ {{ number_format($producto->precio, 0, ',', '.') }}@if($producto->unidad === 'metro') / metro @endif
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm">

                            @if($producto->stock <= 0 || $producto->stock <= $producto->stock_critico)

                                <span
                                    title="Stock actual: {{ $producto->stock }} / Stock crítico: {{ $producto->stock_critico }}"
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 cursor-help"
                                >
                                    {{ $producto->stock }}
                                </span>

                            @else

                                <span
                                    title="Stock actual: {{ $producto->stock }} / Stock crítico: {{ $producto->stock_critico }}"
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 cursor-help"
                                >
                                    {{ $producto->stock }}
                                </span>

                            @endif

                        </td>

                        <td class="space-x-2 whitespace-nowrap px-6 py-4 text-center text-sm font-medium">

                            @if(auth()->user()->rol === 'Administrador')

                                <a
                                    href="{{ route('productos.edit', $producto->id_producto) }}"
                                    title="Editar producto"
                                    aria-label="Editar producto"
                                    class="inline-flex rounded-md p-2 text-[#9f2f25] hover:bg-[#f7e8e6] hover:text-[#721d18]"
                                >

                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
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
                                    action="{{ route('productos.destroy', $producto->id_producto, absolute: false) }}"
                                    method="POST"
                                    class="inline-block"
                                    onsubmit="return confirm('¿Estás seguro de eliminar este producto?');"
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        title="Eliminar producto"
                                        aria-label="Eliminar producto"
                                        class="inline-flex rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-900"
                                    >

                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
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

                            <a
                                href="{{ route('productos.etiqueta', $producto->id_producto) }}"
                                target="_blank"
                                title="Ver etiqueta y código de barras"
                                aria-label="Ver etiqueta y código de barras"
                                class="inline-flex rounded-md p-2 text-[#9f2f25] hover:bg-[#f7e8e6] hover:text-[#721d18]"
                            >

                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 5v4m0 6v4m4-14v14m4-14v4m0 6v4m4-14v14m4-14v4m0 6v4"
                                    />
                                </svg>

                            </a>

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>

    <div class="mt-6">
        {{ $productos->links() }}
    </div>

</div>

@endsection