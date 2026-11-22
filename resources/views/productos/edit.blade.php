@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Editar Producto: {{ $producto->nombre }}</h2>

    @if (session('validation_error') || $errors->any())
        <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md" role="alert">
            <span class="text-red-600 text-lg" aria-hidden="true">!</span>
            <div>
                <p class="font-semibold">No se pudo actualizar el producto</p>
                <p class="text-sm mt-1">{{ session('validation_error', $errors->first()) }}</p>
            </div>
        </div>
    @endif

    <form action="{{ route('productos.update', $producto->id_producto, absolute: false) }}" method="POST" enctype="multipart/form-data" class="space-y-4" data-codigo-original="{{ $producto->codigo_barra }}" onsubmit="return validateProductForm(this)">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700">Código de Barra</label>
            <input type="text" name="codigo_barra" value="{{ old('codigo_barra', $producto->codigo_barra) }}" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Nombre del Repuesto</label>
            <input type="text" name="nombre" value="{{ old('nombre', $producto->nombre) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
        </div>

        <div class="product-validation-error hidden items-start gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md" role="alert" aria-live="polite">
            <span class="text-red-600 text-lg" aria-hidden="true">!</span>
            <div>
                <p class="font-semibold">Revisa los datos del producto</p>
                <p class="product-validation-message text-sm mt-1"></p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Categoría</label>
            <select name="id_categoria" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id_categoria }}" {{ old('id_categoria', $producto->id_categoria) == $cat->id_categoria ? 'selected' : '' }}>
                        {{ $cat->nombre_categoria }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="unidad" class="block text-sm font-medium text-gray-700">Unidad de venta</label>
                <select id="unidad" name="unidad" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
                    <option value="unidad" @selected(old('unidad', $producto->unidad) === 'unidad')>Unidad</option>
                    <option value="metro" @selected(old('unidad', $producto->unidad) === 'metro')>Metro</option>
                </select>
            </div>
            <div class="flex items-center gap-2 pt-6">
                <input type="hidden" name="activo" value="0">
                <input id="activo" type="checkbox" name="activo" value="1" @checked((bool) old('activo', $producto->activo)) class="rounded border-gray-300 text-[#b52f25] shadow-sm focus:ring-[#b52f25]">
                <label for="activo" class="text-sm font-medium text-gray-700">Activo y visible en el catálogo</label>
            </div>
        </div>

        <div id="motivo-desactivacion-container" class="{{ $producto->activo && !(bool) old('activo', $producto->activo) ? '' : 'hidden' }}">
            <label for="motivo_desactivacion" class="block text-sm font-medium text-gray-700">Motivo de desactivación</label>
            <textarea id="motivo_desactivacion" name="motivo_desactivacion" rows="3" maxlength="1000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">{{ old('motivo_desactivacion') }}</textarea>
        </div>

        @if($producto->stock > 0)
            <p id="aviso-stock-desactivacion" class="{{ $producto->activo && !(bool) old('activo', $producto->activo) ? '' : 'hidden' }} rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                El repuesto tiene {{ $producto->stock }} unidades. Al desactivarlo, el stock se conservará en inventario y dejará de estar disponible en el catálogo.
            </p>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700">Descripción</label>
            <textarea name="descripcion" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">{{ old('descripcion', $producto->descripcion) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Imagen del producto (opcional)</label>
            <input type="file" name="imagen" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
            @if($producto->imagen)<img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" class="mt-3 h-24 w-24 object-cover">@endif
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="modo_precio" class="block text-sm font-medium text-gray-700">Modo de precio</label>
                <select id="modo_precio" name="modo_precio" required class="mt-1 block w-full rounded-md border border-gray-300 p-2">
                    <option value="manual" @selected(old('modo_precio', $producto->modo_precio ?? 'manual') === 'manual')>Manual</option>
                    <option value="automatico" @selected(old('modo_precio', $producto->modo_precio) === 'automatico')>Automático por costo y margen</option>
                </select>
            </div>
            <div>
                <label for="margen_objetivo" class="block text-sm font-medium text-gray-700">Margen objetivo sobre precio neto (%)</label>
                <input id="margen_objetivo" type="number" step="0.01" name="margen_objetivo" value="{{ old('margen_objetivo', $producto->margen_objetivo ?? 30) }}" min="0" max="999.99" required class="mt-1 block w-full rounded-md border border-gray-300 p-2">
            </div>
            <div>
                <label for="costo_neto_unitario" class="block text-sm font-medium text-gray-700">Costo neto promedio</label>
                <input id="costo_neto_unitario" type="number" step="0.01" name="costo_neto_unitario" value="{{ old('costo_neto_unitario', $producto->costo_promedio_neto) }}" min="0" max="99999999.99" class="mt-1 block w-full rounded-md border border-gray-300 p-2">
            </div>
            <div>
                <label for="precio_venta" class="block text-sm font-medium text-gray-700">Precio final de venta con IVA</label>
                <input id="precio_venta" type="number" step="0.01" name="precio" value="{{ old('precio', $producto->precio) }}" min="0" max="99999999.99" class="mt-1 block w-full rounded-md border border-gray-300 p-2">
                <p id="precio_recomendado" class="mt-1 hidden text-xs text-gray-600"></p>
            </div>
            <div>
                <label for="stock" class="block text-sm font-medium text-gray-700">Stock</label>
                <input id="stock" type="number" name="stock" value="{{ old('stock', $producto->stock) }}" required min="0" class="mt-1 block w-full rounded-md border border-gray-300 p-2">
            </div>
            <div>
                <label for="stock_critico" class="block text-sm font-medium text-gray-700">Stock Crítico</label>
                <input id="stock_critico" type="number" name="stock_critico" value="{{ old('stock_critico', $producto->stock_critico) }}" required min="0" class="mt-1 block w-full rounded-md border border-gray-300 p-2">
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-4">
            <a href="{{ route('productos.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Cancelar</a>
            <button type="submit" class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d]">Actualizar Producto</button>
        </div>
    </form>
</div>

<script>
    const modoPrecio = document.getElementById('modo_precio');
    const costoPromedio = document.getElementById('costo_neto_unitario');
    const margenObjetivo = document.getElementById('margen_objetivo');
    const precioVenta = document.getElementById('precio_venta');
    const precioRecomendado = document.getElementById('precio_recomendado');

    function actualizarPrecioProducto() {
        const automatico = modoPrecio.value === 'automatico';
        costoPromedio.required = automatico;
        precioVenta.readOnly = automatico;
        precioVenta.required = !automatico;
        precioRecomendado.classList.toggle('hidden', !automatico);

        if (automatico) {
            const costo = Number(costoPromedio.value || 0);
            const margen = Number(margenObjetivo.value || 0);
            if (costo > 0) {
                const recomendado = Math.round(costo * (1 + margen / 100) * 1.19 * 100) / 100;
                precioVenta.value = recomendado.toFixed(2);
                precioRecomendado.textContent = `Precio recomendado: $${recomendado.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} con IVA`;
            } else {
                precioVenta.value = '';
                precioRecomendado.textContent = 'Ingresa el costo neto promedio para calcular el precio.';
            }
        }
    }

    modoPrecio.addEventListener('change', actualizarPrecioProducto);
    costoPromedio.addEventListener('input', actualizarPrecioProducto);
    margenObjetivo.addEventListener('input', actualizarPrecioProducto);
    actualizarPrecioProducto();

    const activoProducto = document.getElementById('activo');
    const motivoDesactivacionContainer = document.getElementById('motivo-desactivacion-container');
    const motivoDesactivacion = document.getElementById('motivo_desactivacion');
    const avisoStockDesactivacion = document.getElementById('aviso-stock-desactivacion');
    const productoEstabaActivo = @json((bool) $producto->activo);

    function actualizarAvisosDesactivacion() {
        const seDesactiva = productoEstabaActivo && !activoProducto.checked;

        motivoDesactivacionContainer.classList.toggle('hidden', !seDesactiva);
        motivoDesactivacion.required = seDesactiva;
        if (avisoStockDesactivacion) {
            avisoStockDesactivacion.classList.toggle('hidden', !seDesactiva);
        }
    }

    activoProducto.addEventListener('change', actualizarAvisosDesactivacion);
    actualizarAvisosDesactivacion();

</script>
<script type="application/json" id="nombres-productos">@json($nombresExistentes)</script>
<script src="{{ asset('js/producto-form.js') }}"></script>
@endsection