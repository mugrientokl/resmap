@extends('layouts.app')

@section('content')
    <style>
        .hero-dot[aria-current="true"] {
            background-color: #f6b941;
            box-shadow: 0 0 0 3px rgba(246, 185, 65, .25), 0 2px 8px rgba(0, 0, 0, .25);
            transform: scaleY(1.35);
        }

        .kits-track { scrollbar-width: none; }
        .kits-track::-webkit-scrollbar { display: none; }
    </style>
    <main class="-mt-6">
        <section class="hero-bleed relative isolate min-h-svh overflow-hidden bg-[#8f241d]" aria-label="Servicios destacados">
            <div id="hero-slides" class="absolute inset-0">
                <article class="hero-slide absolute inset-0 opacity-100 transition-opacity duration-700" data-slide="0" aria-hidden="false">
                    <div class="absolute inset-0 bg-[linear-gradient(115deg,rgba(82,17,13,.94),rgba(143,36,29,.72)),url('https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=2200&q=85')] bg-cover bg-center"></div>
                    <div class="hero-content relative mx-auto flex min-h-svh max-w-7xl items-end pb-20 pt-32"><div class="max-w-3xl text-white"><div class="flex flex-wrap items-center gap-6"><p class="m-0 text-sm font-bold uppercase tracking-[.3em] text-[#f6b941]">Suministros</p><a href="#catalogo-destacado" class="hero-action inline-flex items-center rounded-full bg-[#f6b941] px-6 py-3 font-bold text-[#17211f] shadow-lg transition hover:bg-white">Ver <span class="ml-3">↓</span></a></div><h1 class="max-w-2xl text-5xl font-black leading-[.95] tracking-tight md:text-7xl">Repuestos eléctricos, sin perder tiempo.</h1><p class="mt-7 max-w-xl text-lg leading-8 text-white/80">Explora el catálogo RESMAP y envíanos tu solicitud. Nuestro equipo confirma disponibilidad y te contacta directamente.</p></div></div>
                </article>
                @foreach([
                    ['Mantención', 'Mantención preventiva para seguir avanzando.', 'Anticipa fallas y mantén tus equipos listos para trabajar.', 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?auto=format&fit=crop&w=2200&q=85'],
                    ['Reparación', 'Reparaciones que devuelven tu equipo al trabajo.', 'Cuéntanos qué ocurrió y coordinamos una atención técnica.', 'https://images.unsplash.com/photo-1581092795360-fd1ca04f0952?auto=format&fit=crop&w=2200&q=85'],
                    ['Soldadura', 'Soldadura precisa para soluciones resistentes.', 'Recibe orientación para resolver tu requerimiento con el acabado correcto.', 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=2200&q=85'],
                ] as $indice => [$tipo, $titulo, $descripcion, $imagen])
                    <article class="hero-slide absolute inset-0 opacity-0 transition-opacity duration-700" data-slide="{{ $indice + 1 }}" aria-hidden="true" inert>
                        <div class="absolute inset-0 bg-[linear-gradient(115deg,rgba(82,17,13,.94),rgba(143,36,29,.62)),url('{{ $imagen }}')] bg-cover bg-center"></div>
                        <div class="hero-content relative mx-auto flex min-h-svh max-w-7xl items-end pb-20 pt-32"><div class="max-w-3xl text-white"><div class="flex flex-wrap items-center gap-6"><p class="m-0 text-sm font-bold uppercase tracking-[.3em] text-[#f6b941]">Servicios RESMAP</p><a href="{{ route('servicios.index', ['tipo_servicio' => $tipo]) }}" class="hero-action inline-flex items-center rounded-full bg-[#f6b941] px-6 py-3 font-bold text-[#17211f] shadow-lg transition hover:bg-white">Solicitar {{ $tipo }} <span class="ml-3">→</span></a></div><h2 class="max-w-2xl text-5xl font-black leading-[.95] tracking-tight md:text-7xl">{{ $titulo }}</h2><p class="mt-7 max-w-xl text-lg leading-8 text-white/80">{{ $descripcion }}</p></div></div>
                    </article>
                @endforeach
            </div>
            <button type="button" id="hero-previous" class="group absolute inset-y-0 left-0 z-10 flex w-1/5 items-center justify-center text-white/80" aria-label="Lámina anterior"><span class="rounded-full border border-white/40 px-4 py-2 text-2xl opacity-0 transition group-hover:bg-white group-hover:text-[#8f241d] group-hover:opacity-100">←</span></button>
            <button type="button" id="hero-next" class="group absolute inset-y-0 right-0 z-10 flex w-1/5 items-center justify-center text-white/80" aria-label="Siguiente lámina"><span class="rounded-full border border-white/40 px-4 py-2 text-2xl opacity-0 transition group-hover:bg-white group-hover:text-[#8f241d] group-hover:opacity-100">→</span></button>
            <div class="absolute bottom-7 left-1/2 z-20 flex -translate-x-1/2 gap-2" aria-label="Seleccionar lámina">@foreach(range(0, 3) as $indice)<button type="button" class="hero-dot h-2.5 w-10 rounded-full bg-white/45 transition duration-300 hover:bg-white/80" data-slide-target="{{ $indice }}" aria-label="Ir a la lámina {{ $indice + 1 }}" aria-current="{{ $indice === 0 ? 'true' : 'false' }}"></button>@endforeach</div>
        </section>

        <section id="catalogo-destacado" class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
            <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div><p class="text-sm font-bold uppercase tracking-[.25em] text-[#9f2f25]">Explora por especialidad</p><h2 class="mt-2 text-4xl font-black tracking-tight">Lo que más se mueve</h2></div>
                <a href="{{ route('catalogo.index') }}" class="font-bold text-[#9f2f25] hover:text-[#721d18]">Ver catálogo completo →</a>
            </div>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($categorias as $categoria)
                    <a href="{{ route('catalogo.index', ['categoria' => $categoria->id_categoria]) }}" class="group border-t-4 border-[#b52f25] bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                        <div class="flex items-center justify-between"><h3 class="text-xl font-black">{{ $categoria->nombre_categoria }}</h3><span class="text-2xl text-[#f6b941]">↗</span></div>
                        <p class="mt-3 text-sm text-gray-500">{{ $categoria->productos_count }} productos disponibles</p>
                    </a>
                @endforeach
            </div>
            <section class="mt-16 border-y border-[#e8c8c3] py-10" aria-labelledby="kits-sets-title">
                <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                    <div><p class="text-sm font-bold uppercase tracking-[.25em] text-[#9f2f25]">Soluciones listas para usar</p><h2 id="kits-sets-title" class="mt-2 text-3xl font-black tracking-tight">Kits y sets</h2><p class="mt-2 max-w-xl text-gray-600">Encuentra herramientas y conjuntos completos para resolver tu trabajo en menos pasos.</p></div>
                    <a href="{{ $kitsSetsCategory ? route('catalogo.index', ['categoria' => $kitsSetsCategory->id_categoria]) : route('catalogo.index') }}" class="font-bold text-[#9f2f25] hover:text-[#721d18]">Ver todos los kits y sets →</a>
                </div>
                <div class="relative mt-8 px-12">
                    <button type="button" id="kits-previous" class="absolute left-0 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center border border-[#d9aaa3] bg-[#f8f5f2] text-xl text-[#8f241d] transition hover:bg-[#f7e8e6]" aria-label="Ver kits y sets anteriores" title="Anterior">←</button>
                    <div id="kits-track" class="kits-track flex cursor-grab select-none snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth pb-3 touch-pan-y">
                    @foreach($kitsYSets as $producto)
                        <article class="flex min-w-[82%] snap-start flex-col justify-between bg-white p-5 shadow-sm sm:min-w-[45%] lg:min-w-[23%]">
                            <div>@if($producto->imagen)<img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" class="mb-5 h-40 w-full object-cover">@else<div class="mb-5 flex h-40 items-center justify-center bg-[#f7e8e6] text-sm font-bold uppercase tracking-widest text-[#9f2f25]">Kit / set</div>@endif<p class="text-xs font-bold uppercase tracking-wider text-[#9f2f25]">{{ $producto->categoria->nombre_categoria ?? 'Repuesto' }}</p><h3 class="mt-3 min-h-12 font-bold">{{ $producto->nombre }}</h3></div>
                            <div class="mt-5 flex items-center justify-between gap-3"><p class="text-xl font-black text-[#8f241d]">$ {{ number_format($producto->precio, 0, ',', '.') }}</p><div id="selector-kit-{{ $producto->id_producto }}" data-nombre="{{ e($producto->nombre) }}"><button type="button" data-agregar-kit data-producto-id="{{ $producto->id_producto }}" data-producto-nombre="{{ e($producto->nombre) }}" class="rounded-md bg-[#b52f25] px-3 py-2 text-sm font-bold text-white hover:bg-[#8f241d]">Agregar</button></div></div>
                        </article>
                    @endforeach
                    </div>
                    <button type="button" id="kits-next" class="absolute right-0 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center border border-[#d9aaa3] bg-[#f8f5f2] text-xl text-[#8f241d] transition hover:bg-[#f7e8e6]" aria-label="Ver más kits y sets" title="Siguiente">→</button>
                </div>
                <div id="kits-carrito-acceso" class="mt-6 hidden justify-end"><button type="button" onclick="abrirCarrito()" class="inline-flex items-center gap-3 rounded-md bg-[#8f241d] px-5 py-3 font-bold text-white shadow-sm transition hover:bg-[#721d18]">Ver carrito <span id="kits-carrito-contador" class="rounded-full bg-white px-2 py-0.5 text-xs text-[#8f241d]">0</span> <span aria-hidden="true">→</span></button></div>
            </section>
        </section>
    </main>

    <div id="carrito-modal" class="fixed inset-0 z-50 hidden bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="carrito-titulo"><div class="ml-auto flex h-full w-full max-w-lg flex-col bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-[#e8c8c3] p-5"><h2 id="carrito-titulo" class="text-2xl font-black text-[#8f241d]">Tu carrito</h2><button type="button" onclick="cerrarCarrito()" class="text-2xl text-[#8f241d]" aria-label="Cerrar carrito">&times;</button></div><div id="carrito-items" class="flex-1 space-y-3 overflow-y-auto p-5"></div><div class="border-t border-[#e8c8c3] p-5"><a href="{{ route('catalogo.index') }}" class="inline-block w-full rounded-md bg-[#b52f25] p-3 text-center font-bold text-white hover:bg-[#8f241d]">Ir a catálogo para solicitar</a></div></div></div>

    <script>
        const carritoStorageKey = 'resmap-carrito';
        const leerCarrito = () => { try { return JSON.parse(window.localStorage.getItem(carritoStorageKey) || '{}'); } catch { return {}; } };
        const seleccion = leerCarrito();
        function guardarCarrito() { window.localStorage.setItem(carritoStorageKey, JSON.stringify(seleccion)); }
        function agregar(id, nombre) { seleccion[id] = seleccion[id] || { id_producto: id, cantidad: 0, nombre }; seleccion[id].cantidad = Math.min(99, seleccion[id].cantidad + 1); guardarCarrito(); renderCarrito(); }
        function cambiarCantidad(id, cambio) { if (!seleccion[id]) return; seleccion[id].cantidad += cambio; if (seleccion[id].cantidad <= 0) delete seleccion[id]; guardarCarrito(); renderCarrito(); }
        function establecerCantidad(id, valor) { if (!seleccion[id]) return; seleccion[id].cantidad = Math.max(1, Math.min(99, Number.parseInt(valor, 10) || 1)); guardarCarrito(); renderCarrito(); }
        function renderCarrito() { const items = Object.values(seleccion); document.getElementById('contador-carrito') ? document.getElementById('contador-carrito').textContent = items.reduce((total, item) => total + item.cantidad, 0) : null; document.getElementById('carrito-items').innerHTML = items.length ? items.map(item => `<div class="flex items-center justify-between gap-3 border-b border-[#f0d7d3] pb-3"><span class="font-semibold text-[#241817]">${item.nombre}</span><div class="flex items-center gap-2"><button type="button" onclick="cambiarCantidad(${item.id_producto}, -1)" class="h-8 w-8 rounded-full bg-[#f7e8e6] font-bold text-[#8f241d]">-</button><input type="number" min="1" max="99" value="${item.cantidad}" onchange="establecerCantidad(${item.id_producto}, this.value)" class="h-8 w-12 border border-[#d9aaa3] text-center font-bold text-[#8f241d]"><button type="button" onclick="cambiarCantidad(${item.id_producto}, 1)" class="h-8 w-8 rounded-full bg-[#b52f25] font-bold text-white">+</button></div></div>`).join('') : '<p class="py-8 text-center text-gray-500">Tu carrito está vacío.</p>'; document.querySelectorAll('[id^="selector-kit-"]').forEach(selector => { const id = Number(selector.id.replace('selector-kit-', '')); const item = seleccion[id]; selector.innerHTML = item ? `<div class="flex items-center gap-2 rounded-md border border-[#e8c8c3] p-1"><button type="button" onclick="cambiarCantidad(${id}, -1)" class="h-8 w-8 rounded bg-[#f7e8e6] font-bold text-[#8f241d]">−</button><input type="number" min="1" max="99" value="${item.cantidad}" onchange="establecerCantidad(${id}, this.value)" class="h-8 w-12 border border-[#d9aaa3] text-center font-bold text-[#8f241d]"><button type="button" onclick="cambiarCantidad(${id}, 1)" class="h-8 w-8 rounded bg-[#b52f25] font-bold text-white">+</button></div>` : `<button type="button" data-agregar-kit data-producto-id="${id}" data-producto-nombre="${selector.dataset.nombre || ''}" class="rounded-md bg-[#b52f25] px-3 py-2 text-sm font-bold text-white hover:bg-[#8f241d]">Agregar</button>`; }); const kitsCarritoAcceso = document.getElementById('kits-carrito-acceso'); const total = items.reduce((sum, item) => sum + item.cantidad, 0); if (kitsCarritoAcceso) { kitsCarritoAcceso.classList.toggle('hidden', total === 0); kitsCarritoAcceso.classList.toggle('flex', total > 0); } const kitsCarritoContador = document.getElementById('kits-carrito-contador'); if (kitsCarritoContador) kitsCarritoContador.textContent = total; }
        document.addEventListener('click', event => { const button = event.target.closest('[data-agregar-kit]'); if (button) agregar(Number(button.dataset.productoId), button.dataset.productoNombre); });
        function abrirCarrito() { document.getElementById('carrito-modal').classList.remove('hidden'); renderCarrito(); }
        function cerrarCarrito() { document.getElementById('carrito-modal').classList.add('hidden'); }
        renderCarrito();
        document.getElementById('carrito-modal').addEventListener('click', event => { if (event.target.id === 'carrito-modal') cerrarCarrito(); });


            const slides = [...document.querySelectorAll('.hero-slide')];
            const dots = [...document.querySelectorAll('.hero-dot')];
            let activeSlide = 0;
            let timer;

            const showSlide = (index) => {
                activeSlide = (index + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => {
                    const isActive = slideIndex === activeSlide;
                    slide.classList.toggle('opacity-100', isActive);
                    slide.classList.toggle('opacity-0', !isActive);
                    slide.setAttribute('aria-hidden', String(!isActive));
                    slide.inert = !isActive;
                });
                dots.forEach((dot, dotIndex) => dot.setAttribute('aria-current', String(dotIndex === activeSlide)));
            };

            const startTimer = () => {
                window.clearInterval(timer);
                timer = window.setInterval(() => showSlide(activeSlide + 1), 3000);
            };

            document.getElementById('hero-previous')?.addEventListener('click', () => { showSlide(activeSlide - 1); startTimer(); });
            document.getElementById('hero-next')?.addEventListener('click', () => { showSlide(activeSlide + 1); startTimer(); });
            dots.forEach((dot) => dot.addEventListener('click', () => { showSlide(Number(dot.dataset.slideTarget)); startTimer(); }));
            document.querySelector('a[href="#catalogo-destacado"]')?.addEventListener('click', (event) => {
                event.preventDefault();
                document.getElementById('catalogo-destacado')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            document.querySelectorAll('.hero-action').forEach((action) => {
                action.addEventListener('mouseenter', () => window.clearInterval(timer));
                action.addEventListener('mouseleave', startTimer);
            });

            const kitsTrack = document.getElementById('kits-track');
            const kitsCards = kitsTrack ? [...kitsTrack.children] : [];
            let kitsGroupWidth = 0;
            let kitsCardStep = 0;

            if (kitsTrack && kitsCards.length > 1) {
                kitsCardStep = kitsCards[1].offsetLeft - kitsCards[0].offsetLeft;
                kitsGroupWidth = kitsCardStep * kitsCards.length;
                const cardsBefore = kitsCards.map((card) => card.cloneNode(true));
                const cardsAfter = kitsCards.map((card) => card.cloneNode(true));
                kitsTrack.prepend(...cardsBefore);
                kitsTrack.append(...cardsAfter);
                kitsTrack.scrollLeft = kitsGroupWidth;
                kitsTrack.addEventListener('scroll', () => {
                    if (kitsTrack.scrollLeft < kitsGroupWidth * .5) {
                        kitsTrack.scrollLeft += kitsGroupWidth;
                    } else if (kitsTrack.scrollLeft > kitsGroupWidth * 1.5) {
                        kitsTrack.scrollLeft -= kitsGroupWidth;
                    }
                }, { passive: true });
            }

            const kitsScroll = (direction) => kitsTrack?.scrollBy({ left: direction * kitsCardStep, behavior: 'smooth' });
            document.getElementById('kits-previous')?.addEventListener('click', () => kitsScroll(-1));
            document.getElementById('kits-next')?.addEventListener('click', () => kitsScroll(1));

            let kitsDragging = false;
            let kitsDragStart = 0;
            let kitsScrollStart = 0;
            let kitsDragTarget = 0;
            let kitsDragFrame;
            const animateKitsDrag = () => {
                if (!kitsTrack || !kitsDragging) {
                    kitsDragFrame = undefined;
                    return;
                }

                const distance = kitsDragTarget - kitsTrack.scrollLeft;
                kitsTrack.scrollLeft += distance * .28;
                kitsDragFrame = Math.abs(distance) > .5 ? requestAnimationFrame(animateKitsDrag) : undefined;
            };
            kitsTrack?.addEventListener('pointerdown', (event) => {
                if (event.target.closest?.('button, a')) return;
                kitsDragging = true;
                kitsDragStart = event.clientX;
                kitsScrollStart = kitsTrack.scrollLeft;
                kitsDragTarget = kitsScrollStart;
                kitsTrack.setPointerCapture(event.pointerId);
                kitsTrack.classList.replace('cursor-grab', 'cursor-grabbing');
                kitsTrack.classList.remove('scroll-smooth');
                kitsTrack.classList.remove('snap-x', 'snap-mandatory');
            });
            kitsTrack?.addEventListener('pointermove', (event) => {
                if (!kitsDragging) return;
                event.preventDefault();
                kitsDragTarget = kitsScrollStart - (event.clientX - kitsDragStart);
                if (!kitsDragFrame) kitsDragFrame = requestAnimationFrame(animateKitsDrag);
            });
            const stopKitsDragging = () => {
                if (!kitsDragging) return;
                kitsDragging = false;
                window.cancelAnimationFrame(kitsDragFrame);
                kitsDragFrame = undefined;
                if (kitsTrack && kitsCardStep) kitsTrack.scrollTo({ left: Math.round(kitsTrack.scrollLeft / kitsCardStep) * kitsCardStep, behavior: 'smooth' });
                kitsTrack?.classList.replace('cursor-grabbing', 'cursor-grab');
                kitsTrack?.classList.add('scroll-smooth');
                kitsTrack?.classList.add('snap-x', 'snap-mandatory');
            };
            kitsTrack?.addEventListener('pointerup', stopKitsDragging);
            kitsTrack?.addEventListener('pointercancel', stopKitsDragging);
            startTimer();
        })();
    </script>
@endsection
