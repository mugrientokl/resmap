@extends('layouts.app')

@section('content')
<style>
    [data-fondo-option].is-selected {
        border-color: #b52f25;
        background: #fff4f1;
        box-shadow: 0 0 0 3px rgba(181, 47, 37, .12);
    }
</style>
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Administración</p>
        <h1 class="text-3xl font-black text-gray-900">Configuración visual</h1>
        <p class="mt-2 text-sm text-gray-600">Elige el fondo principal del sistema o agrega una imagen personalizada.</p>
    </div>

    @if (session('success'))
        <div class="rounded-lg border-l-4 border-green-600 bg-green-50 p-4 text-sm font-semibold text-green-900" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border-l-4 border-red-600 bg-red-50 p-4 text-sm font-semibold text-red-900" role="alert">{{ session('error') }}</div>
    @endif

    <section class="rounded-2xl border border-[#e1c3bd] bg-white/80 p-5 shadow-lg backdrop-blur-md">
        <h2 class="text-xl font-black text-gray-900">Fondos disponibles</h2>
        <form method="POST" action="{{ route('configuracion.fondo.actualizar') }}" class="mt-5">
            @csrf
            @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fondos as $fondo)
                    <label data-fondo-option data-fondo-aplicado="{{ $fondoActivo === $fondo['id'] ? '1' : '0' }}" class="group cursor-pointer rounded-xl border-2 p-2 transition {{ $fondoActivo === $fondo['id'] ? 'is-selected border-[#b52f25] bg-[#fff4f1]' : 'border-transparent bg-white/70 hover:border-[#d9aaa3]' }}">
                        <input type="radio" name="fondo" value="{{ $fondo['id'] }}" class="sr-only" @checked($fondoActivo === $fondo['id'])>
                        <div class="aspect-video overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                            <img src="{{ $fondo['url'] }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 px-1">
                            <span class="font-bold text-gray-900">{{ $fondo['nombre'] }}</span>
                            <span data-fondo-badge class="rounded-full px-2 py-1 text-xs font-bold {{ $fondoActivo === $fondo['id'] ? 'bg-[#b52f25] text-white' : 'hidden' }}">
                                {{ $fondoActivo === $fondo['id'] ? 'Activo' : '' }}
                            </span>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('fondo')
                <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p>
            @enderror
            <button type="submit" class="mt-5 rounded-lg bg-[#b52f25] px-5 py-3 font-bold text-white shadow-md transition hover:bg-[#8f241d]">Aplicar fondo</button>
        </form>
    </section>

    <section class="rounded-2xl border border-[#e1c3bd] bg-white/80 p-5 shadow-lg backdrop-blur-md">
        <h2 class="text-xl font-black text-gray-900">Agregar fondo</h2>
        <form method="POST" action="{{ route('configuracion.fondos.subir') }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <label for="fondo" class="block text-sm font-bold text-gray-800">Imagen JPG, PNG o WebP</label>
            <input id="fondo" name="fondo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-lg border border-[#d9aaa3] bg-white p-3 text-sm">
            @error('fondo')
                <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
            @enderror
            <button type="submit" class="mt-4 rounded-lg border border-[#b52f25] px-5 py-3 font-bold text-[#8f241d] transition hover:bg-[#fff4f1]">Subir y convertir a WebP</button>
        </form>
    </section>

    @if (collect($fondos)->contains(fn (array $fondo): bool => str_starts_with($fondo['id'], 'personalizado:')))
        <section class="rounded-2xl border border-[#e1c3bd] bg-white/80 p-5 shadow-lg backdrop-blur-md">
            <h2 class="text-xl font-black text-gray-900">Fondos personalizados</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fondos as $fondo)
                    @continue(! str_starts_with($fondo['id'], 'personalizado:'))
                    @php($personalizadoId = substr($fondo['id'], strlen('personalizado:')))
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <img src="{{ $fondo['url'] }}" alt="{{ $fondo['nombre'] }}" class="aspect-video w-full object-cover">
                        <div class="flex items-center justify-between gap-3 p-3">
                            <span class="text-sm font-bold text-gray-900">{{ $fondo['nombre'] }}</span>
                            <form method="POST" action="{{ route('configuracion.fondos.eliminar', $personalizadoId) }}" onsubmit="return confirm('¿Eliminar este fondo personalizado?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

<script>
    document.querySelectorAll('[data-fondo-option]').forEach((option) => {
        const radio = option.querySelector('input[type="radio"]');
        const badge = option.querySelector('[data-fondo-badge]');

        radio.addEventListener('change', () => {
            document.querySelectorAll('[data-fondo-option]').forEach((otherOption) => {
                const otherBadge = otherOption.querySelector('[data-fondo-badge]');
                otherOption.classList.remove('is-selected');
                otherOption.style.borderColor = '';
                otherOption.style.backgroundColor = '';
                otherOption.style.boxShadow = '';

                if (otherOption.dataset.fondoAplicado === '1') {
                    otherBadge.textContent = 'Activo';
                    otherBadge.className = 'rounded-full bg-[#b52f25] px-2 py-1 text-xs font-bold text-white';
                } else {
                    otherBadge.textContent = '';
                    otherBadge.className = 'hidden';
                }
            });

            option.classList.add('is-selected');
            badge.textContent = 'Seleccionado';
            badge.className = 'rounded-full bg-[#721d18] px-2 py-1 text-xs font-bold text-white';
        });
    });
</script>
@endsection
