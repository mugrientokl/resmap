@extends('layouts.app')

@section('content')
<div class="bg-white shadow-md rounded-lg overflow-hidden p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Categorías de Repuestos</h2>
        <button onclick="openCreateModal()" class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d] font-medium">+ Nueva Categoría</button>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('success') }}
        </div>
    @endif

    @if(session('validation_error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <p class="font-medium">No se pudo guardar la categoría:</p>
            <p>{{ session('validation_error') }}</p>
        </div>
    @endif

    <div class="mx-auto max-w-5xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-center">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">ID</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Nombre de Categoría</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Descripción</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($categorias as $cat)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cat->id_categoria }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $cat->nombre_categoria }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $cat->descripcion ?? 'Sin descripción' }}</td>
                    <td class="space-x-2 whitespace-nowrap px-6 py-4 text-center text-sm font-medium">
                        <button onclick="openEditModal('{{ $cat->id_categoria }}', '{{ addslashes($cat->nombre_categoria) }}', '{{ addslashes($cat->descripcion ?? '') }}')" title="Editar categoría" aria-label="Editar categoría" class="inline-flex rounded-md p-2 text-[#9f2f25] hover:bg-[#f7e8e6] hover:text-[#721d18]"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 3.487 3.651 3.651M4 20h4l10.862-10.862a2.587 2.587 0 0 0-3.651-3.651L4.349 16.349A2 2 0 0 0 4 17.763V20Z" /></svg></button>
                        
                        <form action="{{ route('categorias.destroy', $cat->id_categoria, absolute: false) }}" method="POST" class="inline-block" data-confirm-message="¿Seguro que deseas eliminar esta categoría? Esta acción no se puede deshacer.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="Eliminar categoría" aria-label="Eliminar categoría" class="inline-flex rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-900"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18" /></svg></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $categorias->links() }}
    </div>
</div>

<div id="createModal" class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-50" hidden>
    <div role="dialog" aria-modal="true" aria-labelledby="create-modal-title" class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 overflow-hidden">
        <form action="{{ route('categorias.store', absolute: false) }}" method="POST" onsubmit="return validateCategoryForm(this)">
            @csrf
            <input type="hidden" name="form_type" value="create">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="create-modal-title" class="text-lg font-bold text-gray-800">Nueva Categoría</h3>
                <button type="button" onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>

            <div class="category-validation-error hidden mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md" role="alert">
                <p class="font-medium">No se puede guardar la categoría</p>
                <p class="validation-message text-sm mt-1"></p>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label for="nombre_categoria" class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Categoría</label>
                    <input type="text" id="nombre_categoria" name="nombre_categoria" value="{{ old('nombre_categoria') }}" 
                        class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-[#b52f25]"
                        style="border-color: {{ $errors->has('nombre_categoria') && old('form_type') != 'edit' ? '#ef4444' : '#d1d5db' }}">
                    
                    @if($errors->has('nombre_categoria') && old('form_type') != 'edit')
                        <p class="text-red-500 text-xs mt-1">{{ $errors->first('nombre_categoria') }}</p>
                    @endif
                </div>

                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripción (Opcional)</label>
                    <textarea id="descripcion" name="descripcion" rows="3" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#b52f25]">{{ old('descripcion') }}</textarea>
                </div>
            </div>

            <div class="px-6 py-3 bg-gray-50 flex justify-end space-x-2">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 text-sm font-medium">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-[#b52f25] text-white rounded-md hover:bg-[#8f241d] text-sm font-medium">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-50" hidden>
    <div role="dialog" aria-modal="true" aria-labelledby="edit-modal-title" class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 overflow-hidden">
        <form id="editForm" method="POST" onsubmit="return validateCategoryForm(this)">
            @csrf
            @method('PUT')
            <input type="hidden" name="form_type" value="edit">
            <input type="hidden" id="edit_categoria_id" name="categoria_id" value="{{ old('categoria_id') }}">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="edit-modal-title" class="text-lg font-bold text-gray-800">Editar Categoría</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 font-bold text-xl">&times;</button>
            </div>

            <div class="category-validation-error hidden mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md" role="alert">
                <p class="font-medium">No se puede actualizar la categoría</p>
                <p class="validation-message text-sm mt-1"></p>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label for="edit_nombre_categoria" class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Categoría</label>
                    <input type="text" id="edit_nombre_categoria" name="nombre_categoria" value="{{ old('nombre_categoria') }}"
                        class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        style="border-color: {{ $errors->has('nombre_categoria') && old('form_type') == 'edit' ? '#ef4444' : '#d1d5db' }}">
                    
                    @if($errors->has('nombre_categoria') && old('form_type') == 'edit')
                        <p class="text-red-500 text-xs mt-1">{{ $errors->first('nombre_categoria') }}</p>
                    @endif
                </div>

                <div>
                    <label for="edit_descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripción (Opcional)</label>
                    <textarea id="edit_descripcion" name="descripcion" rows="3" 
                        class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        style="border-color: {{ $errors->has('descripcion') && old('form_type') == 'edit' ? '#ef4444' : '#d1d5db' }}">{{ old('descripcion') }}</textarea>
                    @if($errors->has('descripcion') && old('form_type') == 'edit')
                        <p class="text-red-500 text-xs mt-1">{{ $errors->first('descripcion') }}</p>
                    @endif
                </div>
            </div>

            <div class="px-6 py-3 bg-gray-50 flex justify-end space-x-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 text-sm font-medium">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-[#b52f25] text-white rounded-md hover:bg-[#8f241d] text-sm font-medium">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        const modal = document.getElementById('createModal');
        modal.hidden = false;
        modal.classList.add('flex');
    }
    function closeCreateModal() {
        const modal = document.getElementById('createModal');
        modal.classList.remove('flex');
        modal.hidden = true;
    }

    function openEditModal(id, nombre, descripcion) {
        document.getElementById('editForm').action = "/categorias/" + id;
        document.getElementById('edit_nombre_categoria').value = nombre;
        document.getElementById('edit_descripcion').value = descripcion;
        const modal = document.getElementById('editModal');
        modal.hidden = false;
        modal.classList.add('flex');
    }
    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.classList.remove('flex');
        modal.hidden = true;
    }

    function validateCategoryForm(form) {
        let categoryName = form.querySelector('input[name="nombre_categoria"]').value.trim();
        let validationError = form.querySelector('.category-validation-error');
        let validationMessage = validationError.querySelector('.validation-message');

        validationError.classList.add('hidden');
        validationMessage.textContent = '';

        if (!categoryName) {
            showCategoryValidationError(validationError, validationMessage, 'El nombre de la categoría es obligatorio.');
            return false;
        }

        if (/^\d+$/.test(categoryName)) {
            showCategoryValidationError(validationError, validationMessage, 'El nombre de la categoría no puede contener únicamente números; debe incluir al menos una letra.');
            return false;
        }

        return true;
    }

    function showCategoryValidationError(validationError, validationMessage, message) {
        validationMessage.textContent = message;
        validationError.classList.remove('hidden');
    }

    document.addEventListener("DOMContentLoaded", function() {
        @if (session('validation_error') || $errors->any())
            @if(old('form_type') == 'edit')
                let editCategoriaId = document.getElementById('edit_categoria_id').value;
                if (editCategoriaId) {
                    document.getElementById('editForm').action = "/categorias/" + editCategoriaId;
                }
                const editModal = document.getElementById('editModal');
                editModal.hidden = false;
                editModal.classList.add('flex');
            @else
                openCreateModal();
            @endif
        @endif
    });
</script>
@endsection