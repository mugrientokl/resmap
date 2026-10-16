@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Editar Cliente: {{ $cliente->nombre }}</h2>

    <form action="{{ route('clientes.update', $cliente->id_cliente) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700">RUT</label>
            <input type="text" name="rut" value="{{ old('rut', $cliente->rut) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Razón Social / Nombre</label>
            <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                <input type="email" name="correo" value="{{ old('correo', $cliente->correo) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Dirección / Faena</label>
            <input type="text" name="direccion" value="{{ old('direccion', $cliente->direccion) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2">
        </div>

        <div class="flex justify-end space-x-3 pt-4">
            <a href="{{ route('clientes.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Cancelar</a>
            <button type="submit" class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d]">Guardar Cambios</button>
        </div>
    </form>
</div>
@endsection