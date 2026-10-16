@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Crear Nuevo Producto / Repuesto</h2>

    @if (session('validation_error') || $errors->any())
        <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md" role="alert">
            <span class="text-red-600 text-lg" aria-hidden="true">!</span>
            <div>
                <p class="font-semibold">No se pudo guardar el producto</p>
                <p class="text-sm mt-1">{{ session('validation_error', $errors->first()) }}</p>
            </div>
        </div>
    @endif

    <form action="{{ route('productos.store', absolute: false) }}" method="POST" enctype="multipart/form-data" class="space-y-4" onsubmit="return validateProductForm(this)">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700">Código de Barra</label>
            <input type="text" name="codigo_barra" value="{{ old('codigo_barra') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Nombre del Repuesto</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
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
                <option value="">Seleccione una categoría</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat->id_categoria }}" {{ old('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>
                        {{ $cat->nombre_categoria }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Descripción</label>
            <textarea name="descripcion" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">{{ old('descripcion') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Imagen del producto (opcional)</label>
            <input type="file" name="imagen" accept="image/*" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Precio</label>
                <input type="number" step="0.01" name="precio" value="{{ old('precio') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Stock Inicial</label>
                <input type="number" name="stock" value="{{ old('stock') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Stock Crítico</label>
                <input type="number" name="stock_critico" value="{{ old('stock_critico') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-4">
            <a href="{{ route('productos.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Cancelar</a>
            <button type="submit" class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d]">Guardar Producto</button>
        </div>
    </form>
</div>

<script>
    function validateProductForm(form) {
        let errorBox = form.querySelector('.product-validation-error');
        let errorMessage = form.querySelector('.product-validation-message');
        let productName = form.querySelector('input[name="nombre"]').value.trim();

        errorBox.classList.add('hidden');
        errorMessage.textContent = '';

        if (!productName) {
            errorMessage.textContent = 'El nombre del repuesto es obligatorio.';
            errorBox.classList.remove('hidden');
            return false;
        }

        if (/^\d+$/.test(productName)) {
            errorMessage.textContent = 'El nombre del repuesto no puede contener únicamente números; debe incluir al menos una letra.';
            errorBox.classList.remove('hidden');
            return false;
        }

        return true;
    }
</script>
@endsection