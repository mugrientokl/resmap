@extends('layouts.app')

@section('content')
    <style>
        .about-fullbleed {
            margin-left: 0;
            margin-right: 0;
            width: 100%;
        }
        #catalogo-destacado {
            width: 100%;
            max-width: none !important;
            margin-left: 0;
            margin-right: 0;
            padding-left: 0;
            padding-right: 0;
        }
        .home-section-gutter {
            padding-left: var(--content-gutter);
            padding-right: var(--content-gutter);
        }
        .kits-all-button {
            border-radius: 9999px !important;
        }

        body.public-navigation .hero-bleed,
        body.public-navigation .hero-content { min-height: max(100svh, 42rem); }

        @media (max-width: 1023px) {
            body.public-navigation .hero-content { padding-top: 4rem; }
            body.public-navigation .hero-content h1,
            body.public-navigation .hero-content h2 { font-size: clamp(1.75rem, 4vw, 2.25rem); }
        }

        @media (max-width: 639px) {
            .hero-bleed, .hero-content { min-height: max(100svh, 48rem); }
            body.public-navigation .hero-bleed,
            body.public-navigation .hero-content { min-height: max(100svh, 48rem); }
        }
    </style>
    <div class="-mt-6">
        <section class="hero-bleed relative isolate min-h-svh overflow-hidden bg-[#8f241d]" aria-label="Servicios destacados">
            <div class="absolute inset-0 bg-[linear-gradient(115deg,rgba(82,17,13,.94),rgba(143,36,29,.72)),url('https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=2200&q=85')] bg-cover bg-center"></div>
            <div class="hero-content relative mx-auto flex min-h-svh max-w-7xl items-end pb-20 pt-32">
                <div class="max-w-3xl text-white">
                    <p class="m-0 text-sm font-bold uppercase tracking-[.3em] text-[#f6b941]">Soluciones para mantenerte trabajando</p>
                    <h1 class="mt-4 max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">Repuestos confiables y servicio especializado para tu maquinaria.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-7 text-white/90">Atendemos maquinaria pesada, agrícola y forestal con repuestos de primer nivel, mantención y soluciones técnicas coordinadas a tu operación.</p>
                    <a href="{{ route('catalogo.index') }}" class="hero-action mt-7 inline-flex items-center rounded-full bg-[#f6b941] px-6 py-3 font-bold text-[#17211f] shadow-lg transition hover:bg-white">Ver catálogo completo <span class="ml-3">→</span></a>
                </div>
            </div>
        </section>

        <section id="catalogo-destacado" class="pb-10">
            <section class="about-fullbleed relative isolate flex min-h-[29rem] items-center overflow-hidden border-y-4 border-[#f6b941] text-white shadow-[0_20px_50px_rgba(36,24,23,.18)]" aria-labelledby="servicios-resmap-title">
                <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(24,24,24,.88),rgba(24,24,24,.58)),url('https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=2200&q=85')] bg-cover bg-center"></div>
                <div class="relative mx-auto w-full px-6 py-16 text-center sm:px-10 lg:px-16">
                    <h2 id="servicios-resmap-title" class="mx-auto max-w-5xl text-4xl font-black leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl">
                        Servicio técnico de confianza para mantener tu maquinaria operativa
                    </h2>
                    <p class="mx-auto mt-5 max-w-4xl text-base leading-7 text-white/90 sm:text-lg">
                        Realizamos mantención preventiva, reparación y soldadura con atención especializada para maquinaria pesada,
                        agrícola y forestal. Coordinamos cada trabajo para reducir detenciones y devolver tus equipos al trabajo con seguridad.
                    </p>
                    <a href="{{ route('servicios.index') }}" class="group mt-8 inline-flex min-h-14 items-center justify-center gap-3 rounded-xl bg-[#f6b941] px-8 py-4 font-black text-[#17211f] shadow-[0_12px_28px_rgba(246,185,65,.18)] transition duration-300 hover:-translate-y-1 hover:bg-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.5a5 5 0 0 0-6.3 6.3l-5 5a2 2 0 0 0 2.8 2.8l5-5a5 5 0 0 0 6.3-6.3l-3.1 3.1-2.6-2.6 2.9-3.3Z"/></svg>
                        Ver / solicitar servicios
                        <span aria-hidden="true" class="transition-transform group-hover:translate-x-1">→</span>
                    </a>
                </div>
            </section>

            <section class="home-section-gutter mt-16 border-y border-[#e8c8c3] py-10" aria-labelledby="kits-sets-title">
                <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                    <div><p class="text-sm font-bold uppercase tracking-[.25em] text-[#9f2f25]">Soluciones listas para usar</p><h2 id="kits-sets-title" class="mt-2 text-3xl font-black tracking-tight">Kits y sets</h2><p class="mt-2 max-w-xl text-gray-600">Encuentra herramientas y conjuntos completos para resolver tu trabajo en menos pasos.</p></div>
                </div>
                @if($kitsYSets->isNotEmpty())
                    <div id="kits-track" class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-3">
                        @foreach($kitsYSets as $producto)
                            <article class="kits-card group flex h-full min-w-0 flex-col rounded-[1.75rem] border border-[#ead8d5] bg-white p-5 shadow-[0_10px_30px_rgba(36,24,23,.06)] transition duration-300 hover:-translate-y-2 hover:border-[#d9aaa3] hover:shadow-[0_18px_40px_rgba(143,36,29,.16)] sm:p-6">
                                <div class="flex flex-1 flex-col">
                                    @if($producto->imagen)
                                        <div class="relative mb-5 aspect-square w-full overflow-hidden rounded-2xl">
                                            <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" class="h-full w-full object-cover">
                                        </div>
                                    @else
                                        <div class="mb-5 flex aspect-square w-full items-center justify-center rounded-2xl bg-[#f7e8e6]">
                                            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#8f241d] text-white shadow-md" aria-hidden="true">
                                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M7 7v10m10-10v10M5 17h14M9 11h6"/></svg>
                                            </span>
                                        </div>
                                    @endif
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#9f2f25]">{{ $producto->categoria->nombre_categoria ?? 'Kit / set' }}</p>
                                    <h3 class="mt-3 min-h-14 text-lg font-black leading-6">{{ $producto->nombre }}</h3>
                                </div>
                                <div class="mt-auto flex items-center justify-between gap-3 pt-5">
                                    <p class="text-xl font-black text-[#8f241d]">$ {{ number_format($producto->precio, 0, ',', '.') }} @if($producto->unidad === 'metro')<small class="text-sm font-semibold">/ metro</small>@endif</p>
                                    <div id="selector-kit-{{ $producto->id_producto }}" data-nombre="{{ e($producto->nombre) }}"><button type="button" data-agregar-kit data-producto-id="{{ $producto->id_producto }}" data-producto-nombre="{{ e($producto->nombre) }}" class="rounded-full bg-[#b52f25] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#8f241d]">Agregar</button></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-8 flex justify-center">
                        <a href="{{ $kitsSetsCategory ? route('catalogo.index', ['categoria' => $kitsSetsCategory->id_categoria]) : route('catalogo.index') }}" class="kits-all-button inline-flex min-h-14 items-center justify-center gap-3 bg-[#8f241d] px-9 py-4 text-center text-base font-black text-white shadow-[0_10px_25px_rgba(143,36,29,.22)] transition hover:bg-[#721d18] sm:px-10">Ver todos los kits y sets <span aria-hidden="true">→</span></a>
                    </div>
                    <div id="kits-cotizacion-acceso" class="mt-6 hidden justify-end"><a href="{{ route('catalogo.index', ['open_cotizacion' => 'true']) }}" class="inline-flex items-center gap-3 rounded-md bg-[#8f241d] px-5 py-3 font-bold text-white shadow-sm transition hover:bg-[#721d18]">Ir a cotizar <span id="kits-cotizacion-contador" class="rounded-full bg-white px-2 py-0.5 text-xs text-[#8f241d]">0</span> <span aria-hidden="true">→</span></a></div>
                @endif
            </section>
        </section>
    </div>

    <div id="carrito-modal" class="fixed inset-0 z-50 hidden bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="carrito-titulo">
        <div class="ml-auto flex h-full w-full max-w-lg flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-[#e8c8c3] p-5">
                <h2 id="carrito-titulo" class="text-2xl font-black text-[#8f241d]">Tu selección para cotizar</h2>
                <button type="button" onclick="cerrarCarrito()" class="text-2xl text-[#8f241d]" aria-label="Cerrar selección">&times;</button>
            </div>
            <div id="carrito-items" class="flex-1 space-y-3 overflow-y-auto p-5"></div>
            <div class="border-t border-[#e8c8c3] p-5">
                <a href="{{ route('catalogo.index') }}" class="inline-block w-full rounded-md bg-[#b52f25] p-3 text-center font-bold text-white hover:bg-[#8f241d]">Ir al catálogo y cotizar</a>
            </div>
        </div>
    </div>

    <script>
        const carritoStorageKey = 'resmap-carrito';
        const leerCarrito = () => {
            try {
                return JSON.parse(window.localStorage.getItem(carritoStorageKey) || '{}');
            } catch {
                return {};
            }
        };
        const seleccion = leerCarrito();

        window.escaparHTML = (valor) => {
            const elemento = document.createElement('span');
            elemento.textContent = String(valor);

            return elemento.innerHTML
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        window.guardarCarrito = () => {
            window.localStorage.setItem(carritoStorageKey, JSON.stringify(seleccion));
        };

        window.agregar = (id, nombre) => {
            seleccion[id] = seleccion[id] || { id_producto: id, cantidad: 0, nombre };
            seleccion[id].cantidad = Math.min(99, seleccion[id].cantidad + 1);
            guardarCarrito();
            renderCarrito();
        };

        window.cambiarCantidad = (id, cambio) => {
            if (!seleccion[id]) return;
            seleccion[id].cantidad += cambio;
            if (seleccion[id].cantidad <= 0) delete seleccion[id];
            guardarCarrito();
            renderCarrito();
        };

        window.establecerCantidad = (id, valor) => {
            if (!seleccion[id]) return;
            seleccion[id].cantidad = Math.max(1, Math.min(99, Number.parseInt(valor, 10) || 1));
            guardarCarrito();
            renderCarrito();
        };

        window.renderCarrito = () => {
            const items = Object.values(seleccion);
            const carritoItems = document.getElementById('carrito-items');
            if (carritoItems) {
                carritoItems.innerHTML = items.length
                    ? items.map(item => `<div class="flex items-center justify-between gap-3 border-b border-[#f0d7d3] pb-3"><span class="font-semibold text-[#241817]">${escaparHTML(item.nombre)}</span><div class="flex items-center gap-2"><button type="button" onclick="cambiarCantidad(${Number(item.id_producto)}, -1)" class="h-8 w-8 rounded-full bg-[#f7e8e6] font-bold text-[#8f241d]">-</button><input type="number" min="1" max="99" value="${Number(item.cantidad) || 1}" onchange="establecerCantidad(${Number(item.id_producto)}, this.value)" class="h-8 w-12 border border-[#d9aaa3] text-center font-bold text-[#8f241d]"><button type="button" onclick="cambiarCantidad(${Number(item.id_producto)}, 1)" class="h-8 w-8 rounded-full bg-[#b52f25] font-bold text-white">+</button></div></div>`).join('')
                    : '<p class="py-8 text-center text-gray-500">Aún no has seleccionado repuestos.</p>';
            }

            document.querySelectorAll('[id^="selector-kit-"]').forEach(selector => {
                const id = Number(selector.id.replace('selector-kit-', ''));
                const item = seleccion[id];
                selector.innerHTML = item
                    ? `<div class="flex items-center gap-2 rounded-md border border-[#e8c8c3] p-1"><button type="button" onclick="cambiarCantidad(${id}, -1)" class="h-8 w-8 rounded bg-[#f7e8e6] font-bold text-[#8f241d]">−</button><input type="number" min="1" max="99" value="${item.cantidad}" onchange="establecerCantidad(${id}, this.value)" class="h-8 w-12 border border-[#d9aaa3] text-center font-bold text-[#8f241d]"><button type="button" onclick="cambiarCantidad(${id}, 1)" class="h-8 w-8 rounded bg-[#b52f25] font-bold text-white">+</button></div>`
                    : `<button type="button" data-agregar-kit data-producto-id="${id}" data-producto-nombre="${escaparHTML(selector.dataset.nombre || '')}" class="rounded-md bg-[#b52f25] px-3 py-2 text-sm font-bold text-white hover:bg-[#8f241d]">Agregar</button>`;
            });

            const total = items.reduce((sum, item) => sum + item.cantidad, 0);
            const kitsCotizacionAcceso = document.getElementById('kits-cotizacion-acceso');
            if (kitsCotizacionAcceso) {
                kitsCotizacionAcceso.classList.toggle('hidden', total === 0);
                kitsCotizacionAcceso.classList.toggle('flex', total > 0);
            }
            const kitsCotizacionContador = document.getElementById('kits-cotizacion-contador');
            if (kitsCotizacionContador) {
                kitsCotizacionContador.textContent = total;
            }
        };

        window.abrirCarrito = () => {
            document.getElementById('carrito-modal').classList.remove('hidden');
            renderCarrito();
        };

        window.cerrarCarrito = () => {
            document.getElementById('carrito-modal').classList.add('hidden');
        };

        document.addEventListener('click', event => {
            const button = event.target.closest('[data-agregar-kit]');
            if (button) agregar(Number(button.dataset.productoId), button.dataset.productoNombre);
        });

        renderCarrito();
    </script>
@endsection
