@extends('layouts.app')

@section('content')
<style>
    .catalogo-card input[type="number"] {
        background-color: #fff;
    }
    .catalogo-filters select {
        color-scheme: light;
    }
    .catalogo-pagination nav > div {
        flex-wrap: wrap;
    }
    .catalogo-pagination nav > div:nth-child(2) > div:nth-child(2) > span {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem;
    }
    .catalogo-pagination nav p {
        color: #493733;
    }
    .catalogo-pagination nav a,
    .catalogo-pagination nav [aria-current="page"] > span,
    .catalogo-pagination nav [aria-disabled="true"] > span,
    .catalogo-pagination nav > div:first-child > span {
        margin-left: 0;
        border-color: #d9aaa3;
        border-radius: .5rem;
        background-color: #fff;
        color: #8f241d;
    }
    .catalogo-pagination nav a:hover {
        background-color: #f7e8e6;
        color: #721d18;
    }
    .catalogo-pagination nav a:focus-visible {
        outline: 2px solid #8f241d;
        outline-offset: 2px;
    }
    .catalogo-pagination nav [aria-current="page"] > span {
        border-color: #d04438;
        background-color: #b52f25;
        color: #fff;
    }
    .catalogo-pagination nav [aria-disabled="true"] > span,
    .catalogo-pagination nav > div:first-child > span {
        color: #786a67;
    }
</style>
<main class="mx-auto max-w-7xl py-6 pb-32">
    <div class="max-w-[48rem]">
        <p class="text-sm font-bold uppercase tracking-[.2em] text-[#8f241d]">
            Catálogo de repuestos
        </p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-[#241817] sm:text-4xl xl:text-5xl">
            Encuentra lo que necesitas.
        </h1>
    </div>

    @if(session('success'))
        <div id="success-toast"
            class="fixed inset-x-4 top-4 z-60 mx-auto flex max-w-2xl items-start gap-4 rounded-2xl border border-green-300 bg-green-50 p-5 text-green-950 shadow-[0_20px_60px_rgba(36,24,23,0.25)] sm:top-8 sm:gap-5 sm:p-7"
            role="status" aria-live="polite" data-auto-dismiss>
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#e5f5e9] text-[#21713e] sm:h-14 sm:w-14" aria-hidden="true">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xl font-black leading-tight text-green-950 sm:text-2xl">¡Tu solicitud ha sido enviada con éxito!</p>
                <p class="mt-2 text-base leading-relaxed text-green-900">{{ session('success') }}</p>
            </div>
            <button type="button" class="-mr-2 -mt-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-2xl text-green-800 hover:bg-green-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-800" aria-label="Cerrar confirmación" data-dismiss-notification>&times;</button>
        </div>
    @endif

    @if($errors->any() && old('_formulario') !== 'servicio')
        <div id="error-toast"
            class="fixed right-5 top-24 z-60 max-w-sm border-l-4 border-red-700 bg-red-50 p-5 text-red-800 shadow-xl"
            role="alert" data-auto-dismiss>
            <p class="font-black">No se pudo enviar la cotización</p>
            <p class="mt-1 text-sm">{{ $errors->first() }}</p>
        </div>
    @endif

    <form method="GET" action="{{ route('catalogo.index') }}"
        class="resmap-glass catalogo-filters relative z-10 mt-10 grid gap-2.5 rounded-2xl border p-3.5 {{ request()->filled('nombre') || request()->filled('categoria') ? 'md:grid-cols-[minmax(0,1fr)_200px_auto_auto]' : 'md:grid-cols-[minmax(0,1fr)_200px_auto]' }}">

        <div id="catalogo-selector" class="relative min-w-0">
            <label for="catalogo-buscar" class="sr-only">Buscar producto</label>

            <input type="text" id="catalogo-buscar" name="nombre"
                value="{{ request('nombre') }}"
                placeholder="Buscar por nombre o palabra"
                autocomplete="off"
                class="w-full border border-[#d9aaa3] bg-white px-3 py-2 text-[#241817] placeholder:text-[#786a67] focus:border-[#b52f25] focus:outline-2 focus:outline-offset-2 focus:outline-[#8f241d]">

            <div id="catalogo-sugerencias" style="display: none;"
                class="absolute left-0 top-full z-50 mt-1 max-h-40 w-full overflow-y-auto rounded-md border border-[#d9aaa3] bg-white shadow-lg">
            </div>

            @php($datosBuscador = $productosBuscador->map(fn ($p) => [$p->nombre, $p->codigo_origen ?: $p->codigo_barra, $p->id_categoria, $p->id_producto, $p->stock_disponible])->values())
            <script type="application/json" id="catalogo-datos">@json($datosBuscador)</script>
        </div>

        <select id="catalogo-categoria" name="categoria" aria-label="Categoría"
            class="border border-[#d9aaa3] bg-white px-3 py-2 text-[#241817] focus:border-[#b52f25] focus:outline-2 focus:outline-offset-2 focus:outline-[#8f241d]">
            <option value="">Todas las categorías</option>

            @foreach($categorias as $categoria)
                <option value="{{ $categoria->id_categoria }}"
                    @selected(request('categoria') == $categoria->id_categoria)>
                    {{ $categoria->nombre_categoria }}
                </option>
            @endforeach
        </select>

        <button type="submit"
            class="bg-[#b52f25] px-5 py-2 font-bold text-white hover:bg-[#8f241d]">
            Buscar
        </button>
        @if(request()->filled('nombre') || request()->filled('categoria'))
            <a href="{{ route('catalogo.index') }}"
                class="inline-flex items-center justify-center border border-[#d9aaa3] bg-white px-5 py-2 font-bold text-[#8f241d] hover:bg-[#f7e8e6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">
                Limpiar filtros
            </a>
        @endif
    </form>

    <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($productos as $producto)
            <article class="resmap-glass catalogo-card group flex h-full min-w-0 flex-col rounded-[1.75rem] border p-4 text-[#241817] transition duration-300 hover:-translate-y-2 hover:border-[#f08c80] hover:shadow-[0_18px_40px_rgba(143,36,29,.35)]">
                <div class="flex flex-1 flex-col">
                    <div class="relative mb-3 aspect-[4/3] w-full overflow-hidden rounded-2xl border border-[#e8c8c3]">
                        @if($producto->imagen)
                            <img src="{{ asset('storage/'.$producto->imagen) }}"
                                alt="{{ $producto->nombre }}"
                                class="h-full w-full object-contain">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-white/50">
                                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#8f241d] text-white shadow-md" aria-hidden="true">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M7 7v10m10-10v10M5 17h14M9 11h6"/></svg>
                                </span>
                            </div>
                        @endif
                        @if($producto->estaEnOferta())
                            <div class="absolute left-3 top-3 rounded-full bg-[#b52f25] px-3 py-1 text-xs font-black text-white shadow">
                                Oferta -{{ number_format($producto->porcentaje_descuento, 0, ',', '.') }}%
                            </div>
                        @endif
                        @if($producto->stock_disponible <= 0)
                            <div class="absolute inset-0 flex items-center justify-center bg-black/40">
                                <span class="rounded-md bg-[#241817] px-4 py-2 text-sm font-bold text-white">Agotado</span>
                            </div>
                        @endif
                    </div>

                    <p class="text-xs font-bold uppercase tracking-wider text-[#8f241d]">
                        {{ $producto->categoria?->nombre_categoria ?? 'Repuesto' }}
                    </p>

                    <h2 class="mt-3 min-h-14 text-lg font-black leading-6 text-[#241817]">
                        {{ $producto->nombre }}
                    </h2>

                    <p class="mt-2 min-h-4"></p>
                </div>

                <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-5">
                    <span class="text-xl font-black text-[#241817]">
                        @if($producto->estaEnOferta())
                            <span class="mr-2 text-sm font-bold text-gray-400 line-through">$ {{ number_format($producto->precio, 0, ',', '.') }}</span>
                        @endif
                        $ {{ number_format($producto->precioVigente(), 0, ',', '.') }}

                        @if($producto->unidad === 'metro')
                            <small class="text-sm font-semibold">/ metro</small>
                        @endif
                    </span>

                    <div id="selector-{{ $producto->id_producto }}"
                        data-nombre="{{ $producto->nombre }}"
                        data-stock="{{ $producto->stock_disponible }}">

                        <button type="button" data-agregar-producto
                            data-producto-id="{{ $producto->id_producto }}"
                            data-producto-nombre="{{ $producto->nombre }}"
                            data-producto-stock="{{ $producto->stock_disponible }}"
                            @disabled($producto->stock_disponible <= 0)
                            class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white enabled:hover:bg-[#8f241d] disabled:cursor-not-allowed disabled:opacity-50">
                            {{ $producto->stock_disponible <= 0 ? 'Sin stock' : 'Agregar' }}
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-2xl bg-white p-6 text-center text-gray-500">
                No se encontraron productos.
            </p>
        @endforelse
    </div>

    @if($productos->hasPages())
        <div class="resmap-glass catalogo-pagination mt-8 rounded-2xl border p-4">{{ $productos->links() }}</div>
    @endif
</main>

<script>
(() => {
    const buscador = document.getElementById('catalogo-buscar');
    const lista = document.getElementById('catalogo-sugerencias');
    const contenedor = document.getElementById('catalogo-selector');
    const categoria = document.getElementById('catalogo-categoria');

    const normalizar = texto => texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const separarPalabras = texto => normalizar(texto)
        .split(/[^a-z0-9]+/)
        .filter(Boolean);

    const productos = JSON.parse(
        document.getElementById('catalogo-datos').textContent
    ).map(([nombre, codigo, categoria]) => ({
        nombre,
        codigo: codigo || '',
        categoria: String(categoria),
        texto: separarPalabras(`${nombre} ${codigo || ''}`)
    }));

    function cerrar() {
        lista.style.display = 'none';
    }

    function mostrar() {
        const palabrasBuscadas = separarPalabras(buscador.value);
        lista.replaceChildren();

        if (!palabrasBuscadas.length) {
            cerrar();
            return;
        }

        const coincidencias = productos.filter(producto => {
            const coincideCategoria =
                !categoria.value || producto.categoria === categoria.value;

            const coincideNombre = palabrasBuscadas.every(textoBuscado =>
                producto.texto.some(palabra => palabra.includes(textoBuscado))
            );

            return coincideCategoria && coincideNombre;
        }).slice(0, 30);

        if (!coincidencias.length) {
            const mensaje = document.createElement('p');
            mensaje.className = 'px-4 py-3 text-sm text-gray-500';
            mensaje.textContent = 'No se encontraron productos.';
            lista.append(mensaje);
        }

        coincidencias.forEach(producto => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className =
                'block w-full border-b border-gray-100 px-4 py-3 text-left text-sm font-semibold text-[#241817] hover:bg-red-50 focus:bg-red-50';

            const nombre = document.createElement('span');
            nombre.className = 'block';
            nombre.textContent = producto.nombre;
            boton.append(nombre);

            if (producto.codigo) {
                const codigo = document.createElement('span');
                codigo.className = 'block text-xs font-normal text-gray-500';
                codigo.textContent = producto.codigo;
                boton.append(codigo);
            }

            boton.addEventListener('click', () => {
                buscador.value = producto.nombre;
                cerrar();
                buscador.form.requestSubmit();
            });

            lista.append(boton);
        });

        lista.style.display = 'block';
    }

    buscador.addEventListener('input', mostrar);
    buscador.addEventListener('focus', mostrar);
    categoria.addEventListener('change', cerrar);

    contenedor.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            buscador.focus();
            cerrar();
            return;
        }

        if (event.key === 'ArrowDown' && event.target === buscador) {
            mostrar();
            const primeraOpcion = lista.querySelector('button');

            if (primeraOpcion) {
                event.preventDefault();
                primeraOpcion.focus();
            }

            return;
        }

        const opciones = Array.from(lista.querySelectorAll('button'));
        const indice = opciones.indexOf(document.activeElement);

        if (indice !== -1 && event.key === 'ArrowDown') {
            event.preventDefault();
            opciones[(indice + 1) % opciones.length].focus();
        }

        if (indice !== -1 && event.key === 'ArrowUp') {
            event.preventDefault();

            if (indice === 0) {
                buscador.focus();
            } else {
                opciones[indice - 1].focus();
            }
        }
    });

    document.addEventListener('click', event => {
        if (!contenedor.contains(event.target)) cerrar();
    });

    contenedor.addEventListener('focusout', event => {
        if (!contenedor.contains(event.relatedTarget)) cerrar();
    });
})();

</script>

@include('catalogo.seleccion')
@endsection
