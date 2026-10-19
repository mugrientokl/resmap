@extends('layouts.app')

@section('content')
<div class="bg-white shadow-md rounded-lg p-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">
        Movimientos de Inventario
    </h2>

    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Formulario para registrar movimiento -->
    <form action="{{ route('inventario.movimientos.store') }}" method="POST" class="mb-8 p-4 border rounded-lg bg-gray-50">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            
            <!-- Buscador y Selector de producto -->
            <div x-data="{
                search: '',
                selectedId: '{{ old('id_producto') }}',
                filterProducts() {
                    let term = this.search.toLowerCase().trim();
                    let options = this.$refs.selectEl.options;
                    for (let i = 0; i < options.length; i++) {
                        let text = options[i].text.toLowerCase();
                        if (i === 0) continue;
                        options[i].style.display = text.includes(term) ? '' : 'none';
                    }
                }
            }">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar / Seleccionar Producto</label>
                
                <input 
                    type="text" 
                    x-model="search" 
                    @input="filterProducts()" 
                    placeholder="Escribe nombre o categoría..." 
                    class="w-full mb-1 border border-gray-300 rounded-md p-2 text-sm focus:outline-none focus:border-[#b52f25]"
                >

                <select 
                    x-ref="selectEl"
                    name="id_producto" 
                    x-model="selectedId" 
                    class="w-full border-gray-300 rounded-md shadow-sm border p-2 text-sm" 
                    required
                >
                    <option value="">-- Seleccionar producto --</option>
                    @foreach($productos as $prod)
                        @php($catNombre = $prod->categoria->nombre_categoria ?? 'Sin Categoría')
                        <option value="{{ $prod->id_producto }}">
                            {{ $prod->nombre }} | Cat: {{ $catNombre }} | Stock: {{ $prod->stock }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tipo de movimiento -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de movimiento</label>
                <select name="tipo" class="w-full border-gray-300 rounded-md shadow-sm border p-2 text-sm" required>
                    <option value="ingreso" @selected(old('tipo') === 'ingreso')>Ingreso (+)</option>
                    <option value="egreso" @selected(old('tipo') === 'egreso')>Egreso (-)</option>
                    <option value="ajuste" @selected(old('tipo') === 'ajuste')>Ajuste de Stock</option>
                </select>
            </div>

            <!-- Cantidad -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                <input type="number" name="cantidad" value="{{ old('cantidad') }}" min="1" class="w-full border-gray-300 rounded-md shadow-sm border p-2 text-sm" required>
            </div>

            <!-- Select de Motivo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                <select name="motivo" class="w-full border-gray-300 rounded-md shadow-sm border p-2 text-sm" required>
                    <option value="">Seleccione motivo</option>
                    <option value="Compra / Adquisición" @selected(old('motivo') === 'Compra / Adquisición')>Compra / Adquisición</option>
                    <option value="Merma / Daño" @selected(old('motivo') === 'Merma / Daño')>Merma / Daño</option>
                    <option value="Devolución" @selected(old('motivo') === 'Devolución')>Devolución</option>
                    <option value="Ajuste de inventario" @selected(old('motivo') === 'Ajuste de inventario')>Ajuste de inventario</option>
                    <option value="Uso interno" @selected(old('motivo') === 'Uso interno')>Uso interno</option>
                </select>
            </div>

            <!-- Fecha de adquisición o merma -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de adquisición / merma</label>
                <input type="date" name="fecha_evento" value="{{ old('fecha_evento', date('Y-m-d')) }}" class="w-full border-gray-300 rounded-md shadow-sm border p-2 text-sm" required>
            </div>

            <!-- Botón registrar -->
            <div class="flex items-end">
                <button type="submit" class="w-full bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d] font-medium text-sm h-10">
                    Registrar Movimiento
                </button>
            </div>
        </div>
    </form>

    <!-- Tabla de movimientos -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-center text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Fecha Registro</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Fecha Evento</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Producto</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Tipo</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Cantidad</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Stock Resultante</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Motivo</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Usuario</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($movimientos as $mov)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">
                            {{ $mov->created_at ? $mov->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $mov->fecha_evento ? \Carbon\Carbon::parse($mov->fecha_evento)->format('d/m/Y') : '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $mov->producto->nombre ?? 'Producto no encontrado' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full {{ in_array($mov->tipo, ['ingreso', 'adquisicion']) ? 'bg-green-100 text-green-800' : (in_array($mov->tipo, ['egreso', 'merma']) ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ ucfirst($mov->tipo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-bold">{{ abs($mov->cantidad) }}</td>
                        <td class="px-4 py-3">{{ $mov->stock_nuevo }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $mov->motivo }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $mov->usuario->name ?? 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            No hay movimientos de inventario registrados aún.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>
</div>
@endsection