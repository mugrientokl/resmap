@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Panel de administración</h1>
        <p class="mt-1 text-sm text-gray-600">Gestiona las opciones y la configuración del sistema.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['categorias.index', 'Categorías', 'Crea, edita y organiza las categorías de productos.'],
            ['proveedores.index', 'Proveedores', 'Crea y edita los proveedores y sus contactos.'],
            ['usuarios.index', 'Usuarios', 'Administra las cuentas de los usuarios.'],
            ['backups.index', 'Backups', 'Consulta y administra los respaldos.'],
        ] as [$ruta, $titulo, $descripcion])
            <a href="{{ route($ruta) }}" class="block rounded-lg border border-[#e8c8c3] bg-white p-6 shadow-sm hover:border-[#b52f25] hover:shadow-md">
                <h2 class="text-lg font-bold text-[#9f2f25]">{{ $titulo }}</h2>
                <p class="mt-2 text-sm text-gray-600">{{ $descripcion }}</p>
            </a>
        @endforeach
    </div>

    <section class="rounded-lg bg-white p-6 shadow-md" aria-labelledby="navegacion-title">
        <h2 id="navegacion-title" class="text-lg font-bold text-gray-800">Navegación</h2>
        <p class="mt-1 text-sm text-gray-600">Esta configuración se aplica a todos los usuarios con sesión iniciada. En dispositivos móviles, el menú permanece arriba.</p>
        <form action="{{ route('administracion.navegacion') }}" method="POST" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            <fieldset>
                <legend class="mb-2 text-sm font-medium text-gray-700">Posición del menú</legend>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2"><input type="radio" name="posicion_navegacion" value="superior" @checked(old('posicion_navegacion', $posicionNavegacion) === 'superior')> Superior</label>
                    <label class="flex items-center gap-2"><input type="radio" name="posicion_navegacion" value="izquierda" @checked(old('posicion_navegacion', $posicionNavegacion) === 'izquierda')> Lateral izquierda</label>
                </div>
                @error('posicion_navegacion')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>
            <button type="submit" class="rounded-md bg-[#b52f25] px-4 py-2 font-medium text-white hover:bg-[#8f241d]">Guardar configuración</button>
        </form>
    </section>
</div>
@endsection
