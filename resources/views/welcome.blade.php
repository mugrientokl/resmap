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

    </style>
    <div class="-mt-6">
        <section class="hero-bleed relative isolate overflow-hidden bg-transparent" aria-label="Servicios destacados">
            <div class="hero-content relative mx-auto flex items-center pb-6 pt-32 sm:pt-36">
                <div class="grid w-full grid-cols-1 gap-6 md:grid-cols-2 lg:gap-10">
                    <div class="resmap-glass hero-feature flex h-full flex-col rounded-[1.75rem] border p-6 text-[#241817] sm:p-8 lg:p-10">
                        <p class="m-0 text-sm font-bold uppercase tracking-[.15em] text-[#8f241d]">Soluciones para mantenerte trabajando</p>
                        <h1 class="mt-4 text-3xl font-black leading-[1.1] tracking-tight sm:text-4xl xl:text-5xl">Repuestos confiables y servicio especializado para tu maquinaria.</h1>
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
            <section class="home-section-gutter border-y border-[#e8c8c3] py-10" aria-labelledby="kits-sets-title">
                <div>
                    <div class="max-w-[48rem]">
                        <p class="text-base font-bold uppercase tracking-[.2em] text-[#8f241d]">Equipamiento para tu trabajo</p>
                        <h2 id="kits-sets-title" class="mt-3 text-4xl font-black tracking-tight text-[#241817] sm:text-5xl">Kits y sets</h2>
                        <p class="mt-3 max-w-xl text-lg font-medium leading-7 text-[#241817] sm:text-xl sm:leading-8">Encuentra herramientas y conjuntos seleccionados para trabajar con mayor eficiencia.</p>
                    </div>
                </div>
                @if($kitsYSets->isNotEmpty())
                    <div id="kits-track" class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-3">
                        @foreach($kitsYSets as $producto)
                            <article class="resmap-glass kits-card group flex h-full min-w-0 flex-col rounded-[1.75rem] border p-5 text-[#241817] transition duration-300 hover:-translate-y-2 hover:border-[#f08c80] hover:shadow-[0_18px_40px_rgba(143,36,29,.35)] sm:p-6">
                                <div class="flex flex-1 flex-col">
                                    @if($producto->imagen)
                                        <div class="relative mb-5 aspect-square w-full overflow-hidden rounded-2xl border border-[#e8c8c3]">
                                            <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" class="h-full w-full object-cover">
                                        </div>
                                    @else
                                        <div class="mb-5 flex aspect-square w-full items-center justify-center rounded-2xl border border-[#e8c8c3] bg-white/50">
                                            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#8f241d] text-white shadow-md" aria-hidden="true">
                                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M7 7v10m10-10v10M5 17h14M9 11h6"/></svg>
                                            </span>
                                        </div>
                                    @endif
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#8f241d]">{{ $producto->categoria->nombre_categoria ?? 'Kit / set' }}</p>
                                    <h3 class="mt-3 min-h-14 text-lg font-black leading-6">{{ $producto->nombre }}</h3>
                                </div>
                                <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-5">
                                    <p class="text-xl font-black text-[#241817]">$ {{ number_format($producto->precio, 0, ',', '.') }} @if($producto->unidad === 'metro')<small class="text-sm font-semibold">/ metro</small>@endif</p>
                                    <div id="selector-{{ $producto->id_producto }}" data-nombre="{{ $producto->nombre }}" data-stock="{{ $producto->stock_disponible }}"><button type="button" data-agregar-producto data-producto-id="{{ $producto->id_producto }}" data-producto-nombre="{{ $producto->nombre }}" data-producto-stock="{{ $producto->stock_disponible }}" class="rounded-full bg-[#b52f25] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#8f241d]">Agregar</button></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-8 flex justify-center">
                        <a href="{{ $kitsSetsCategory ? route('catalogo.index', ['categoria' => $kitsSetsCategory->id_categoria]) : route('catalogo.index') }}" class="kits-all-button inline-flex min-h-14 items-center justify-center gap-3 bg-[#8f241d] px-9 py-4 text-center text-base font-black text-white shadow-[0_10px_25px_rgba(143,36,29,.22)] transition hover:bg-[#721d18] sm:px-10">Ver todos los kits y sets <span aria-hidden="true">→</span></a>
                    </div>
                @endif
            </section>
        </section>
    </div>

    @include('catalogo.seleccion')
@endsection
