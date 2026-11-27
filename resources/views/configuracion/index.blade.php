@extends('layouts.app')

@section('content')
<style>
    [data-fondo-option].is-selected,
    [data-logo-option].is-selected {
        border-color: #b52f25;
        background: #fff4f1;
        box-shadow: 0 0 0 3px rgba(181, 47, 37, .12);
    }
</style>
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Administración</p>
        <h1 class="text-3xl font-black text-gray-900">Configuración</h1>
    </div>

    @if (session('success'))
        <div class="rounded-lg border-l-4 border-green-600 bg-green-50 p-4 text-sm font-semibold text-green-900" role="status">{{ session('success') }}</div>
    @endif

    <nav class="grid gap-3 sm:grid-cols-2" aria-label="Submódulos de configuración">
        <a href="{{ route('configuracion.index', ['seccion' => 'ajustes-visuales']) }}"
            class="rounded-xl border px-5 py-4 font-bold {{ $seccion === 'ajustes-visuales' ? 'border-[#b52f25] bg-[#fff4f1] text-[#721d18]' : 'border-[#ead8d5] bg-white text-gray-700 hover:bg-[#fffaf9]' }}">
            Ajustes visuales
        </a>
        <a href="{{ route('configuracion.index', ['seccion' => 'notificaciones']) }}"
            class="rounded-xl border px-5 py-4 font-bold {{ $seccion === 'notificaciones' ? 'border-[#b52f25] bg-[#fff4f1] text-[#721d18]' : 'border-[#ead8d5] bg-white text-gray-700 hover:bg-[#fffaf9]' }}">
            Notificaciones
        </a>
    </nav>

    @if ($seccion === 'ajustes-visuales')
        <section class="rounded-2xl border border-[#e1c3bd] bg-white/80 p-5 shadow-lg backdrop-blur-md">
            <h2 class="text-xl font-black text-gray-900">Fondo del sistema</h2>
            <form method="POST" action="{{ route('configuracion.fondo.actualizar') }}" class="mt-5">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($fondos as $fondo)
                        <label data-fondo-option data-fondo-activo="{{ $fondoActivo === $fondo['id'] ? '1' : '0' }}" class="cursor-pointer rounded-xl border-2 p-2 transition {{ $fondoActivo === $fondo['id'] ? 'is-selected border-[#b52f25] bg-[#fff4f1]' : 'border-transparent bg-white hover:border-[#d9aaa3]' }}">
                            <input type="radio" name="fondo" value="{{ $fondo['id'] }}" class="sr-only" @checked($fondoActivo === $fondo['id'])>
                            <img src="{{ $fondo['url'] }}" alt="{{ $fondo['nombre'] }}" class="aspect-video w-full rounded-lg object-cover">
                            <div class="mt-2 flex items-center justify-between gap-2 px-1">
                                <span class="font-bold text-gray-900">{{ $fondo['nombre'] }}</span>
                                <span data-fondo-badge class="rounded-full px-2 py-1 text-xs font-bold {{ $fondoActivo === $fondo['id'] ? 'bg-[#b52f25] text-white' : 'hidden' }}">{{ $fondoActivo === $fondo['id'] ? 'Activo' : '' }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="mt-5 rounded-lg bg-[#b52f25] px-5 py-3 font-bold text-white hover:bg-[#8f241d]">Aplicar fondo</button>
            </form>
            <form method="POST" action="{{ route('configuracion.fondos.subir') }}" enctype="multipart/form-data" class="mt-6 border-t border-[#ead8d5] pt-5">
                @csrf
                <label for="fondo" class="block text-sm font-bold text-gray-800">Agregar fondo personalizado</label>
                <input id="fondo" name="fondo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-lg border border-[#d9aaa3] bg-white p-3 text-sm">
                <button type="submit" class="mt-3 rounded-lg border border-[#b52f25] px-5 py-2.5 font-bold text-[#8f241d]">Subir fondo</button>
            </form>
        </section>

        <section class="rounded-2xl border border-[#e1c3bd] bg-white/80 p-5 shadow-lg backdrop-blur-md">
            <h2 class="text-xl font-black text-gray-900">Logo del sistema</h2>
            <p class="mt-1 text-sm text-gray-600">El logo seleccionado se usa en el panel, la navegación, correos y documentos.</p>
            <form method="POST" action="{{ route('configuracion.logo.actualizar') }}" class="mt-5">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($logos as $logo)
                        <label data-logo-option data-logo-activo="{{ $logoActivo === $logo['id'] ? '1' : '0' }}" class="cursor-pointer rounded-xl border-2 p-4 transition {{ $logoActivo === $logo['id'] ? 'is-selected border-[#b52f25] bg-[#fff4f1]' : 'border-[#ead8d5] bg-white' }}">
                            <input type="radio" name="logo" value="{{ $logo['id'] }}" class="sr-only" @checked($logoActivo === $logo['id'])>
                            <div class="flex h-28 items-center justify-center rounded-lg bg-gray-50 p-3">
                                <img src="{{ $logo['url'] }}" alt="{{ $logo['nombre'] }}" class="max-h-full max-w-full object-contain">
                            </div>
                            <div class="mt-3 flex items-center justify-center gap-2">
                                <span class="text-center font-bold text-gray-900">{{ $logo['nombre'] }}</span>
                                <span data-logo-badge class="rounded-full px-2 py-1 text-xs font-bold {{ $logoActivo === $logo['id'] ? 'bg-[#b52f25] text-white' : 'hidden' }}">{{ $logoActivo === $logo['id'] ? 'Activo' : '' }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="mt-5 rounded-lg bg-[#b52f25] px-5 py-3 font-bold text-white hover:bg-[#8f241d]">Aplicar logo</button>
            </form>
            <form method="POST" action="{{ route('configuracion.logos.subir') }}" enctype="multipart/form-data" class="mt-6 border-t border-[#ead8d5] pt-5">
                @csrf
                <label for="logo" class="block text-sm font-bold text-gray-800">Agregar logo personalizado</label>
                <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-lg border border-[#d9aaa3] bg-white p-3 text-sm">
                <button type="submit" class="mt-3 rounded-lg border border-[#b52f25] px-5 py-2.5 font-bold text-[#8f241d]">Subir y activar logo</button>
            </form>
        </section>

        <section class="rounded-2xl border border-[#e1c3bd] bg-white/80 p-5 shadow-lg backdrop-blur-md">
            <h2 class="text-xl font-black text-gray-900">Tamaño de fuente</h2>
            <p class="mt-1 text-sm text-gray-600">Esta preferencia solo afecta tu vista dentro del acceso interno. Los visitantes externos mantienen el tamaño normal.</p>
            <form method="POST" action="{{ route('configuracion.fuente.actualizar') }}" class="mt-4">
                @csrf
                @method('PUT')
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach (['pequena' => 'Pequeña', 'mediana' => 'Mediana', 'grande' => 'Grande'] as $valor => $nombre)
                        <label class="cursor-pointer rounded-xl border p-4 text-center font-bold {{ $tamanoFuente === $valor ? 'border-[#b52f25] bg-[#fff4f1] text-[#721d18]' : 'border-[#ead8d5] bg-white text-gray-700' }}">
                            <input type="radio" name="tamano_fuente" value="{{ $valor }}" class="mr-2" @checked($tamanoFuente === $valor)>
                            {{ $nombre }}
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="mt-5 rounded-lg bg-[#b52f25] px-5 py-3 font-bold text-white hover:bg-[#8f241d]">Aplicar tamaño</button>
            </form>
        </section>
    @else
        <section class="rounded-2xl border border-[#e1c3bd] bg-white/80 p-5 shadow-lg backdrop-blur-md">
            <h2 class="text-xl font-black text-gray-900">Tipos de notificación</h2>
            <p class="mt-1 text-sm text-gray-600">Activa o desactiva los avisos operativos del sistema.</p>
            <form method="POST" action="{{ route('configuracion.notificaciones.actualizar') }}" class="mt-5 space-y-3">
                @csrf
                @method('PUT')
                @foreach ($notificaciones as $tipo => $configuracion)
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-[#ead8d5] bg-white p-4">
                        <span class="font-bold text-gray-900">{{ $configuracion['nombre'] }}</span>
                        <input type="checkbox" name="notificaciones[]" value="{{ $tipo }}" @checked($configuracion['activo']) class="h-5 w-10 accent-[#b52f25]">
                    </label>
                @endforeach
                <button type="submit" class="mt-3 rounded-lg bg-[#b52f25] px-5 py-3 font-bold text-white hover:bg-[#8f241d]">Guardar notificaciones</button>
            </form>
        </section>
    @endif
</div>

<script>
    document.querySelectorAll('[data-fondo-option]').forEach((option) => {
        const radio = option.querySelector('input[type="radio"]');
        const badge = option.querySelector('[data-fondo-badge]');

        radio.addEventListener('change', () => {
            document.querySelectorAll('[data-fondo-option]').forEach((other) => {
                const otherBadge = other.querySelector('[data-fondo-badge]');
                other.classList.remove('is-selected');
                otherBadge.textContent = other.dataset.fondoActivo === '1' ? 'Activo' : '';
                otherBadge.className = other.dataset.fondoActivo === '1'
                    ? 'rounded-full bg-[#b52f25] px-2 py-1 text-xs font-bold text-white'
                    : 'hidden';
            });
            option.classList.add('is-selected');
            badge.textContent = 'Seleccionado';
            badge.className = 'rounded-full bg-[#721d18] px-2 py-1 text-xs font-bold text-white';
        });
    });

    document.querySelectorAll('[data-logo-option]').forEach((option) => {
        const radio = option.querySelector('input[type="radio"]');
        const badge = option.querySelector('[data-logo-badge]');

        radio.addEventListener('change', () => {
            document.querySelectorAll('[data-logo-option]').forEach((other) => {
                const otherBadge = other.querySelector('[data-logo-badge]');
                other.classList.remove('is-selected');
                otherBadge.textContent = other.dataset.logoActivo === '1' ? 'Activo' : '';
                otherBadge.className = other.dataset.logoActivo === '1'
                    ? 'rounded-full bg-[#b52f25] px-2 py-1 text-xs font-bold text-white'
                    : 'hidden';
            });
            option.classList.add('is-selected');
            badge.textContent = 'Seleccionado';
            badge.className = 'rounded-full bg-[#721d18] px-2 py-1 text-xs font-bold text-white';
        });
    });
</script>
@endsection
