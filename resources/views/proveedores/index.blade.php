@extends('layouts.app')

@section('content')
<div class="rounded-lg bg-white p-6 shadow-md">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-800">Proveedores</h1>
        @if(auth()->user()->tienePermiso('proveedores.crear'))
            <button type="button" id="create-provider" class="rounded-md bg-[#b52f25] px-4 py-2 font-medium text-white hover:bg-[#8f241d]">+ Nuevo proveedor</button>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-center text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Marca o proveedor</th>
                <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Vendedor o contacto</th>
                <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Teléfono</th>
                <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Correo electrónico</th>
                <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Página web</th>
                <th class="px-6 py-3 text-xs font-medium uppercase text-gray-500">Acciones</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($proveedores as $proveedor)
                    <tr>
                        <td class="px-6 py-4 font-medium">{{ $proveedor->nombre }}</td>
                        <td class="px-6 py-4">{{ $proveedor->contacto }}</td>
                        <td class="px-6 py-4">{{ $proveedor->telefono ?: '—' }}</td>
                        <td class="px-6 py-4">{{ $proveedor->correo ?: '—' }}</td>
                        <td class="px-6 py-4">{{ $proveedor->pagina_web ?: '—' }}</td>
                        <td class="px-6 py-4">
                            @if(auth()->user()->tienePermiso('proveedores.editar'))
                                <button type="button" data-edit-provider data-id="{{ $proveedor->id }}" data-nombre="{{ $proveedor->nombre }}" data-contacto="{{ $proveedor->contacto }}" data-telefono="{{ $proveedor->telefono }}" data-correo="{{ $proveedor->correo }}" data-pagina-web="{{ $proveedor->pagina_web }}" class="rounded-md border border-[#d9aaa3] px-3 py-2 font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Editar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-gray-500">No hay proveedores registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $proveedores->links() }}</div>
</div>

<div id="provider-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-black/60 p-4">
    <div role="dialog" aria-modal="true" aria-labelledby="provider-modal-title" class="max-h-full w-full max-w-md overflow-y-auto rounded-lg bg-white shadow-lg">
        <form id="provider-form" method="POST" action="{{ route('proveedores.store') }}" data-store-url="{{ route('proveedores.store') }}" data-update-url="{{ route('proveedores.update', ['proveedor' => '__ID__']) }}">
            @csrf
            <input id="provider-method" type="hidden" name="_method" value="PUT" disabled>
            <input id="provider-form-type" type="hidden" name="form_type" value="{{ old('form_type', 'create') }}">
            <input id="provider-id" type="hidden" name="proveedor_id" value="{{ old('proveedor_id') }}">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 id="provider-modal-title" class="text-lg font-bold text-gray-800">Nuevo proveedor</h2>
                <button type="button" data-close-provider class="text-2xl text-gray-500" aria-label="Cerrar formulario">&times;</button>
            </div>
            @if($errors->any())
                <div class="mx-6 mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="space-y-4 p-6">
                @include('proveedores.campos', ['prefix' => 'provider', 'useOld' => true])
            </div>
            <div class="flex justify-end gap-2 bg-gray-50 px-6 py-3">
                <button type="button" data-close-provider class="rounded-md border border-gray-300 px-4 py-2 text-sm">Cancelar</button>
                <button class="rounded-md bg-[#b52f25] px-4 py-2 text-sm font-bold text-white hover:bg-[#8f241d]">Guardar</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('provider-modal');
        const form = document.getElementById('provider-form');
        const name = document.getElementById('provider-name');
        const contact = document.getElementById('provider-contact');
        const phone = document.getElementById('provider-phone');
        const legacyPhone = document.getElementById('provider-legacy-phone');
        const email = document.getElementById('provider-email');
        const website = document.getElementById('provider-website');
        const type = document.getElementById('provider-form-type');
        const idField = document.getElementById('provider-id');
        const method = document.getElementById('provider-method');
        const title = document.getElementById('provider-modal-title');
        let previousFocus;
        const open = (id, providerName, providerContact, providerPhone = '', providerEmail = '', providerWebsite = '') => {
            previousFocus = document.activeElement;
            type.value = id ? 'edit' : 'create';
            idField.value = id || '';
            form.action = id ? form.dataset.updateUrl.replace('__ID__', encodeURIComponent(id)) : form.dataset.storeUrl;
            method.disabled = !id;
            title.textContent = id ? 'Editar proveedor' : 'Nuevo proveedor';
            name.value = providerName;
            contact.value = providerContact;
            phone.value = window.telefonoNacionalChile(providerPhone);
            legacyPhone.classList.add('hidden');
            email.value = providerEmail;
            website.value = providerWebsite;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            name.focus();
        };
        const close = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            previousFocus?.focus();
        };
        document.getElementById('create-provider')?.addEventListener('click', () => open('', '', ''));
        document.querySelectorAll('[data-edit-provider]').forEach(button => {
            button.addEventListener('click', () => open(button.dataset.id, button.dataset.nombre, button.dataset.contacto, button.dataset.telefono, button.dataset.correo, button.dataset.paginaWeb));
        });
        modal.querySelectorAll('[data-close-provider]').forEach(button => button.addEventListener('click', close));
        modal.addEventListener('click', event => { if (event.target === modal) close(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
        @if($errors->any())
            open(type.value === 'edit' ? idField.value : '', name.value, contact.value, phone.value, email.value, website.value);
        @endif
    });
</script>
@endsection
