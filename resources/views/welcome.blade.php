@extends('layouts.app')

@section('content')
    <style>
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
        .hero-feature {
            transition: transform .3s ease, border-color .3s ease, box-shadow .3s ease;
        }
        .hero-feature:focus-within {
            transform: translateY(-.5rem);
            border-color: #f08c80;
            box-shadow: 0 18px 40px rgba(143, 36, 29, .35);
        }
        @media (hover: hover) {
            .hero-feature:hover {
                transform: translateY(-.5rem);
                border-color: #f08c80;
                box-shadow: 0 18px 40px rgba(143, 36, 29, .35);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .hero-feature { transition: none; }
            .hero-feature:hover,
            .hero-feature:focus-within { transform: none; }
        }
        .kits-all-button {
            border-radius: 9999px !important;
            max-width: 100%;
            min-height: 4.25rem;
            padding: 1rem 3rem;
            font-size: 1.125rem;
        }
        .kits-card input[type="number"] {
            background-color: #fff;
        }
        .offer-heading-panel,
        .offer-card-copy,
        .offer-card-price {
            border: 1px solid rgba(192, 106, 96, .22);
            background: rgba(255, 255, 255, .58);
            -webkit-backdrop-filter: blur(16px) saturate(125%);
            backdrop-filter: blur(16px) saturate(125%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .82);
        }
        .offer-heading-panel {
            max-width: 48rem;
            border-radius: 1.25rem;
            padding: 1.25rem;
        }
        .offer-card-copy {
            min-height: 7.25rem;
            border-radius: 1rem;
            padding: .9rem 1rem;
        }
        .offer-card-price {
            border-radius: 9999px;
            padding: .7rem 1rem;
        }
        #ofertas-track {
            max-width: 100%;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        #ofertas-track::-webkit-scrollbar {
            display: none;
        }
        .offer-track-item {
            flex: 0 0 min(23rem, 85%);
            max-width: 23rem;
        }
        .offer-track-card {
            width: 100%;
            max-width: 23rem;
            padding: 1rem;
        }
        .offer-track-card .offer-card-image {
            aspect-ratio: 4 / 3;
            margin-bottom: .75rem;
        }
        .offer-track-card .offer-card-copy {
            min-height: 0;
        }
        .offer-track-card .offer-card-copy h3 {
            min-height: 3.5rem;
        }
        .offer-track-card .offer-card-footer {
            padding-top: .875rem;
        }
        @media (max-width: 639px) {
            .offer-heading-panel {
                padding: 1rem;
            }
            .offer-heading-panel h2 {
                font-size: 2.25rem;
                line-height: 1.05;
                overflow-wrap: anywhere;
            }
            .offer-card-copy {
                min-height: 0;
            }
            .offer-track-item {
                flex-basis: 78%;
                max-width: 78%;
            }
        }

    </style>
    <div class="-mt-6">
        <section class="hero-bleed relative isolate overflow-hidden bg-transparent" aria-label="Servicios destacados">
            <div class="hero-content relative mx-auto flex items-center pb-6 pt-32 sm:pt-36">
                <div class="grid w-full grid-cols-1 gap-6 md:grid-cols-2 lg:gap-10">
                    <div class="resmap-glass hero-feature flex h-full flex-col rounded-[1.75rem] border p-6 text-[#241817] sm:p-8 lg:p-10">
                        <p class="m-0 text-sm font-bold uppercase tracking-[.15em] text-[#8f241d]">Soluciones para mantenerte trabajando</p>
                        <h1 class="mt-4 text-3xl font-black leading-[1.1] tracking-tight sm:text-4xl xl:text-5xl">Repuestos confiables para tu maquinaria o vehículo automotorizado.</h1>
                        <p class="mb-8 mt-6 text-base leading-7 text-[#493733] sm:text-lg xl:text-xl xl:leading-8">Atendemos maquinaria pesada, agrícola y forestal con repuestos de primer nivel, mantención y soluciones técnicas coordinadas a tu operación.</p>
                        <a href="{{ route('catalogo.index') }}" class="hero-action mt-auto inline-flex min-h-14 items-center self-start rounded-xl bg-[#b52f25] px-6 py-3 font-bold text-white shadow-lg transition hover:bg-[#8f241d]">Ver catálogo completo <span class="ml-3" aria-hidden="true">→</span></a>
                    </div>
                    <section class="resmap-glass hero-feature flex h-full flex-col rounded-[1.75rem] border p-6 text-[#241817] sm:p-8 lg:p-10" aria-labelledby="servicios-resmap-title">
                        <p class="m-0 text-sm font-bold uppercase tracking-[.15em] text-[#8f241d]">Servicio técnico de confianza</p>
                        <h2 id="servicios-resmap-title" class="mt-4 text-3xl font-black leading-[1.1] tracking-tight sm:text-4xl xl:text-5xl">Servicio técnico de confianza para mantener tu maquinaria operativa</h2>
                        <p class="mb-8 mt-6 text-base leading-7 text-[#493733] sm:text-lg xl:text-xl xl:leading-8">
                            Realizamos mantención preventiva, reparación y soldadura con atención especializada para maquinaria pesada,
                            agrícola y forestal. Coordinamos cada trabajo para reducir detenciones y devolver tus equipos al trabajo con seguridad.
                        </p>
                        <a href="{{ route('servicios.index') }}" data-open-servicio class="mt-auto inline-flex min-h-14 items-center justify-center self-start rounded-xl bg-[#b52f25] px-6 py-3 font-bold text-white shadow-lg transition hover:bg-[#8f241d]">
                            Solicitar servicio
                        </a>
                    </section>
                </div>
            </div>
        </section>

        <section id="catalogo-destacado" class="pb-10">
            <section class="home-section-gutter border-y border-[#e8c8c3] py-10" aria-labelledby="ofertas-title">
                <div>
                    <div class="offer-heading-panel">
                        <p class="text-base font-bold uppercase tracking-[.2em] text-[#8f241d]">Aprovecha antes que termine</p>
                        <h2 id="ofertas-title" class="mt-3 text-4xl font-black tracking-tight text-[#241817] sm:text-5xl">Ofertas destacadas</h2>
                        <p class="mt-3 max-w-xl text-lg font-medium leading-7 text-[#241817] sm:text-xl sm:leading-8">Precios especiales seleccionados por nuestro equipo.</p>
                    </div>
                </div>
                @if($productosOferta->isNotEmpty())
                    <div class="relative mt-8">
                        <button type="button" id="ofertas-prev" class="absolute left-0 top-1/2 z-10 hidden -translate-x-1/2 -translate-y-1/2 rounded-full bg-white px-3 py-2 shadow md:inline-flex" aria-label="Oferta anterior">←</button>
                        <div id="ofertas-track" class="flex snap-x snap-mandatory gap-5 overflow-x-auto pb-4">
                        @foreach($productosOferta as $producto)
                            <div class="offer-track-item snap-start">
                            <article class="resmap-glass kits-card offer-track-card group flex h-full min-w-0 flex-col rounded-[1.75rem] border text-[#241817] transition duration-300 hover:-translate-y-2 hover:border-[#f08c80] hover:shadow-[0_18px_40px_rgba(143,36,29,.35)]">
                                <div class="flex flex-1 flex-col">
                                    @if($producto->imagen)
                                        <div class="offer-card-image relative w-full overflow-hidden rounded-2xl border border-[#e8c8c3]">
                                            <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" class="h-full w-full object-contain">
                                            <span class="absolute left-3 top-3 rounded-full bg-[#b52f25] px-3 py-1 text-xs font-black text-white">-{{ number_format($producto->porcentaje_descuento, 0, ',', '.') }}%</span>
                                        </div>
                                    @else
                                        <div class="offer-card-image mb-5 flex w-full items-center justify-center rounded-2xl border border-[#e8c8c3] bg-white/50">
                                            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#8f241d] text-white shadow-md" aria-hidden="true">
                                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M7 7v10m10-10v10M5 17h14M9 11h6"/></svg>
                                            </span>
                                        </div>
                                    @endif
                                    <div class="offer-card-copy">
                                        <p class="text-xs font-bold uppercase tracking-wider text-[#8f241d]">{{ $producto->categoria->nombre_categoria ?? 'Repuesto' }}</p>
                                        <h3 class="mt-3 min-h-14 text-lg font-black leading-6">{{ $producto->nombre }}</h3>
                                    </div>
                                </div>
                                <div class="offer-card-footer mt-auto flex flex-wrap items-center justify-between gap-3">
                                    <div class="offer-card-price">
                                        <p class="text-sm font-bold text-gray-400 line-through">$ {{ number_format($producto->precio, 0, ',', '.') }}</p>
                                        <p class="text-xl font-black text-[#b52f25]">$ {{ number_format($producto->precioVigente(), 0, ',', '.') }} @if($producto->unidad === 'metro')<small class="text-sm font-semibold">/ metro</small>@endif</p>
                                    </div>
                                    <div id="selector-{{ $producto->id_producto }}" data-nombre="{{ $producto->nombre }}" data-stock="{{ $producto->stock_disponible }}"><button type="button" data-agregar-producto data-producto-id="{{ $producto->id_producto }}" data-producto-nombre="{{ $producto->nombre }}" data-producto-stock="{{ $producto->stock_disponible }}" class="rounded-full bg-[#b52f25] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#8f241d]">Agregar</button></div>
                                </div>
                            </article>
                            </div>
                        @endforeach
                        </div>
                        <button type="button" id="ofertas-next" class="absolute right-0 top-1/2 z-10 hidden translate-x-1/2 -translate-y-1/2 rounded-full bg-white px-3 py-2 shadow md:inline-flex" aria-label="Oferta siguiente">→</button>
                    </div>
                    <script>
                        const ofertasTrack = document.getElementById('ofertas-track');
                        if (ofertasTrack) {
                            const ofertasItems = Array.from(ofertasTrack.querySelectorAll('.offer-track-item'));

                            if (ofertasItems.length > 1) {
                                const clonesAlInicio = ofertasItems.map((item) => item.cloneNode(true));
                                const clonesAlFinal = ofertasItems.map((item) => item.cloneNode(true));

                                clonesAlInicio.slice().reverse().forEach((item) => {
                                    item.setAttribute('aria-hidden', 'true');
                                    item.querySelectorAll('[id]').forEach((elemento) => elemento.removeAttribute('id'));
                                    item.querySelectorAll('button').forEach((elemento) => elemento.disabled = true);
                                    ofertasTrack.prepend(item);
                                });
                                clonesAlFinal.forEach((item) => {
                                    item.setAttribute('aria-hidden', 'true');
                                    item.querySelectorAll('[id]').forEach((elemento) => elemento.removeAttribute('id'));
                                    item.querySelectorAll('button').forEach((elemento) => elemento.disabled = true);
                                    ofertasTrack.append(item);
                                });

                                const todosLosItems = () => Array.from(ofertasTrack.querySelectorAll('.offer-track-item'));
                                const posicionInicioOriginal = () => {
                                    const items = todosLosItems();
                                    return items[ofertasItems.length].offsetLeft - ofertasTrack.offsetLeft;
                                };
                                const posicionFinOriginal = () => {
                                    const items = todosLosItems();
                                    return items[ofertasItems.length * 2 - 1].offsetLeft
                                        - ofertasTrack.offsetLeft
                                        + items[ofertasItems.length * 2 - 1].offsetWidth;
                                };
                                const desplazamientoItem = () => {
                                    const primerItem = todosLosItems()[ofertasItems.length];
                                    return primerItem ? primerItem.offsetWidth + 20 : ofertasTrack.clientWidth * 0.8;
                                };

                                requestAnimationFrame(() => {
                                    ofertasTrack.scrollLeft = posicionInicioOriginal();
                                });

                                let reposicionando = false;
                                const normalizarPosicion = () => {
                                    if (reposicionando) {
                                        return;
                                    }

                                    const inicio = posicionInicioOriginal();
                                    const fin = posicionFinOriginal();
                                    if (ofertasTrack.scrollLeft < inicio - desplazamientoItem()) {
                                        reposicionando = true;
                                        ofertasTrack.scrollLeft = fin - desplazamientoItem();
                                        requestAnimationFrame(() => { reposicionando = false; });
                                    } else if (ofertasTrack.scrollLeft >= fin) {
                                        reposicionando = true;
                                        ofertasTrack.scrollLeft = inicio;
                                        requestAnimationFrame(() => { reposicionando = false; });
                                    }
                                };

                                const mover = (direccion) => {
                                    ofertasTrack.scrollBy({
                                        left: direccion * desplazamientoItem(),
                                        behavior: 'smooth',
                                    });
                                    window.setTimeout(normalizarPosicion, 450);
                                };

                                document.getElementById('ofertas-prev')?.addEventListener('click', () => mover(-1));
                                document.getElementById('ofertas-next')?.addEventListener('click', () => mover(1));
                                ofertasTrack.addEventListener('scroll', normalizarPosicion, { passive: true });

                                let temporizadorAutomatico = window.setInterval(() => mover(1), 5000);
                                ofertasTrack.addEventListener('pointerenter', () => window.clearInterval(temporizadorAutomatico));
                                ofertasTrack.addEventListener('pointerleave', () => {
                                    temporizadorAutomatico = window.setInterval(() => mover(1), 5000);
                                });
                            }
                        }
                    </script>
                @endif
            </section>
        </section>
    </div>

    @include('catalogo.seleccion')
@endsection
