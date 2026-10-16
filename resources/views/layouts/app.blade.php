<!DOCTYPE html>
@php($isHome = request()->routeIs('home'))
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESMAP - Sistema de Gestión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }

        body { padding-top: 76px; }
        body.homepage { padding-top: 0; }

        :root { --content-gutter: clamp(1rem, 10vw, 5cm); }

        body > main {
            max-width: none !important;
            padding-left: var(--content-gutter);
            padding-right: var(--content-gutter);
        }

        .max-w-5xl,
        .max-w-6xl,
        .max-w-7xl { max-width: 100% !important; }

        .hero-bleed {
            margin-left: calc(var(--content-gutter) * -1);
            margin-right: calc(var(--content-gutter) * -1);
            width: calc(100% + (var(--content-gutter) * 2));
        }

        .hero-content {
            padding-left: var(--content-gutter);
            padding-right: var(--content-gutter);
        }

        nav > div.max-w-7xl {
            max-width: calc(100% - var(--content-gutter) - var(--content-gutter)) !important;
            padding-left: 0;
            padding-right: 0;
        }

        nav[role="navigation"] a,
        nav[role="navigation"] span {
            border-color: #e8c8c3 !important;
            color: #9f2f25 !important;
            background-color: #fff !important;
        }

        nav[role="navigation"] a:hover {
            background-color: #f7e8e6 !important;
            color: #8f241d !important;
        }

        nav[role="navigation"] span[aria-current="page"] span {
            background-color: #b52f25 !important;
            border-color: #b52f25 !important;
            color: #fff !important;
        }

        .brand-logo { filter: drop-shadow(1px 0 0 #fff) drop-shadow(-1px 0 0 #fff) drop-shadow(0 1px 0 #fff) drop-shadow(0 -1px 0 #fff); }

        #home-navigation { transition: background-color .3s ease, box-shadow .3s ease; }
        #home-navigation.is-scrolled { background-color: #8f241d; box-shadow: 0 4px 16px rgba(36, 24, 23, .2); }
    </style>
</head>
<body class="bg-[#f7f3f0] font-sans antialiased text-[#241817] {{ $isHome ? 'homepage' : '' }}">
    <nav id="{{ $isHome ? 'home-navigation' : 'main-navigation' }}" class="fixed inset-x-0 top-0 z-40 text-white {{ $isHome ? 'bg-transparent' : 'bg-[#8f241d] shadow-lg' }}">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center" aria-label="Ir al inicio de Resmap">
                <img src="{{ asset('images/resmap sin fondo.png') }}" alt="Resmap" class="brand-logo h-12 w-auto max-w-45 object-contain">
            </a>
            @auth
                @php($unreadNotifications = auth()->user()->unreadNotifications()->count())
                @php($pendingRequests = \App\Models\SolicitudWeb::where('estado', 'Pendiente')->count())
                <button type="button" id="mobile-menu-button" class="rounded-md p-2 hover:bg-[#721d18] md:hidden" aria-controls="main-menu" aria-expanded="false" aria-label="Abrir menú">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            @endauth
            @auth
                <div id="main-menu" class="absolute left-0 right-0 top-full hidden bg-[#8f241d] px-4 pb-4 shadow-lg md:static md:flex md:items-center md:gap-4 md:bg-transparent md:p-0 md:shadow-none">
                    <a href="{{ route('dashboard') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]">Dashboard</a>
                    <a href="{{ route('productos.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]">Inventario</a>
                    <a href="{{ route('pos.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]">POS</a>
                    <a href="{{ route('catalogo.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]">Catálogo</a>
                    <a href="{{ route('servicios.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]">Servicios</a>
                    <a href="{{ route('solicitudes.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]">Solicitudes @if($pendingRequests)<span class="ml-1 rounded-full bg-[#ffd5c9] px-2 py-0.5 text-xs text-[#8f241d]">{{ $pendingRequests }}</span>@endif</a>
                    @if(auth()->user()->rol === 'Administrador' || auth()->user()->rol === 'Vendedor')
                        <a href="{{ route('clientes.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]">Clientes</a>
                    @endif
                    <div class="relative mt-2 border-t border-[#b5534b] pt-2 md:mt-0 md:border-0 md:pt-0">
                        <button type="button" id="profile-menu-button" class="flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm hover:bg-[#721d18]" aria-haspopup="true" aria-expanded="false">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#ffd5c9] font-bold text-[#8f241d]">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <span>{{ auth()->user()->name }}</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" /></svg>
                        </button>
                        <div id="profile-menu" class="absolute right-0 mt-1 hidden min-w-48 rounded-md bg-white py-1 text-sm text-gray-700 shadow-xl">
                            @if(auth()->user()->rol === 'Administrador')
                                <a href="{{ route('reportes.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Reportes</a>
                                <a href="{{ route('inventario.movimientos') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Historial de movimientos</a>
                            @endif
                            @if(auth()->user()->rol === 'Administrador')
                                <a href="{{ route('categorias.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Categorías</a>
                                <a href="{{ route('solicitudes.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Solicitudes</a>
                                <a href="{{ route('backups.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Backups</a>
                                <a href="{{ route('usuarios.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Usuarios</a>
                            @endif
                            <a href="{{ route('notificaciones.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Avisos @if($unreadNotifications)<span class="ml-1 rounded-full bg-[#b52f25] px-2 py-0.5 text-xs text-white">{{ $unreadNotifications }}</span>@endif</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-red-700 hover:bg-red-50">Cerrar sesión</button></form>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('catalogo.index') }}" class="rounded px-3 py-2 hover:bg-[#721d18] hover:text-[#ffd5c9]">Catálogo</a>
                                        <a href="{{ route('servicios.index') }}" class="rounded px-3 py-2 hover:bg-[#721d18] hover:text-[#ffd5c9]">Servicios</a>
                    <a href="{{ route('login') }}" class="rounded px-3 py-2 hover:bg-[#721d18] hover:text-[#ffd5c9]">Acceso interno</a>
                </div>
            @endauth
        </div>
    </nav>

    <main class="mx-auto min-h-[calc(100vh-140px)] py-6">
        @yield('content')
    </main>
    <footer class="mt-8 border-t border-[#e8c8c3] bg-white px-4 py-8 text-sm text-gray-600">
        @if($isHome)
            <div class="mx-auto grid max-w-7xl gap-8 text-left md:grid-cols-[1fr_1.2fr] md:items-start">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.25em] text-[#9f2f25]">Contacto RESMAP</p>
                    <h2 class="mt-2 text-2xl font-black text-[#241817]">Hablemos de tu próximo trabajo</h2>
                    <div class="mt-5 grid gap-3 text-[#4f403d] sm:grid-cols-2">
                        <a href="https://wa.me/56976477124" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 transition hover:text-[#9f2f25]" aria-label="Contactar por WhatsApp al +56 9 7647 7124">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#e9f7ee] text-[#168544]" aria-hidden="true">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.5 3.5A11.8 11.8 0 0 0 12.1 0C5.6 0 .3 5.3.3 11.8c0 2.1.6 4.1 1.6 5.8L.2 24l6.5-1.7a11.8 11.8 0 0 0 5.4 1.3h.1c6.5 0 11.8-5.3 11.8-11.8 0-3.1-1.2-6.1-3.5-8.3ZM12.1 21.6h-.1c-1.7 0-3.4-.5-4.8-1.3l-.3-.2-3.8 1 1-3.7-.2-.3a9.8 9.8 0 1 1 8.2 4.5Zm5.4-7.3c-.3-.1-1.8-.9-2.1-1-.3-.1-.5-.1-.7.2l-.9 1.1c-.2.2-.3.2-.6.1a8 8 0 0 1-4-3.5c-.3-.5.3-.5.8-1.6.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.1 3c.1.2 2 3.1 4.8 4.3 2.8 1.2 2.8.8 3.3.8.5 0 1.8-.7 2-1.4.3-.6.3-1.2.2-1.3Z" /></svg>
                            </span>
                            <span>+56 9 7647 7124</span>
                        </a>
                        <a href="https://wa.me/56951523990" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 transition hover:text-[#9f2f25]" aria-label="Contactar por WhatsApp al +56 9 5152 3990">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#e9f7ee] text-[#168544]" aria-hidden="true">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.5 3.5A11.8 11.8 0 0 0 12.1 0C5.6 0 .3 5.3.3 11.8c0 2.1.6 4.1 1.6 5.8L.2 24l6.5-1.7a11.8 11.8 0 0 0 5.4 1.3h.1c6.5 0 11.8-5.3 11.8-11.8 0-3.1-1.2-6.1-3.5-8.3ZM12.1 21.6h-.1c-1.7 0-3.4-.5-4.8-1.3l-.3-.2-3.8 1 1-3.7-.2-.3a9.8 9.8 0 1 1 8.2 4.5Zm5.4-7.3c-.3-.1-1.8-.9-2.1-1-.3-.1-.5-.1-.7.2l-.9 1.1c-.2.2-.3.2-.6.1a8 8 0 0 1-4-3.5c-.3-.5.3-.5.8-1.6.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.1 3c.1.2 2 3.1 4.8 4.3 2.8 1.2 2.8.8 3.3.8.5 0 1.8-.7 2-1.4.3-.6.3-1.2.2-1.3Z" /></svg>
                            </span>
                            <span>+56 9 5152 3990</span>
                        </a>
                        <a href="mailto:resmap.la@gmail.com" class="flex items-center gap-3 transition hover:text-[#9f2f25]">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#f7e8e6] text-[#9f2f25]" aria-hidden="true"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6.75h18v10.5H3zM3 7l9 6 9-6" /></svg></span>
                            <span>resmap.la@gmail.com</span>
                        </a>
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#fff4d8] text-[#b17a00]" aria-hidden="true"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" /><circle cx="12" cy="10" r="2.3" /></svg></span>
                            <span>Los Angeles, Biobio, Chile</span>
                        </div>
                    </div>
                    <section class="mt-8 border-t border-[#f0ddda] pt-6" aria-labelledby="about-us-title">
                        <p id="about-us-title" class="text-xs font-bold uppercase tracking-[.25em] text-[#9f2f25]">Acerca de nosotros</p>
                        <div class="mt-3 flex min-h-24 items-center border border-dashed border-[#e8c8c3] px-4 text-sm italic text-[#8c7772]">
                            Próximamente agregaremos aquí la información sobre RESMAP.
                        </div>
                    </section>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.25em] text-[#9f2f25]">Dónde encontrarnos</p>
                    <p class="mt-2 flex items-start gap-2 text-[#4f403d]"><svg class="mt-0.5 h-5 w-5 shrink-0 text-[#b17a00]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" /><circle cx="12" cy="10" r="2.3" /></svg><span>Av. Gabriela Mistral 1255, Bodega 48,<br>Los Angeles, Region del Biobio, Chile</span></p>
                    <div class="mt-4 overflow-hidden rounded-lg border border-[#e8c8c3] bg-[#f7f3f0] shadow-sm">
                        <iframe title="Ubicacion de RESMAP en Los Angeles, Biobio" src="https://www.google.com/maps?q=-37.4741361,-72.32925&z=17&output=embed" class="h-64 w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query=-37.4741361,-72.32925" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center font-bold text-[#9f2f25] hover:text-[#721d18]">Abrir en Google Maps <span class="ml-2">→</span></a>
                </div>
            </div>
        @endif
        <p class="mt-8 border-t border-[#f0ddda] pt-5 text-center">© RESMAP 2026. Todos Los Derechos Reservados.</p>
    </footer>
    <script>
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mainMenu = document.getElementById('main-menu');
        const profileMenuButton = document.getElementById('profile-menu-button');
        const profileMenu = document.getElementById('profile-menu');
        const homeNavigation = document.getElementById('home-navigation');
        const heroBanner = document.querySelector('.hero-bleed');

        const updateHomeNavigation = () => {
            if (!homeNavigation || !heroBanner) {
                return;
            }

            homeNavigation.classList.toggle('is-scrolled', window.scrollY >= heroBanner.offsetHeight - homeNavigation.offsetHeight);
        };

        window.addEventListener('scroll', updateHomeNavigation, { passive: true });
        window.addEventListener('resize', updateHomeNavigation);
        updateHomeNavigation();

        mobileMenuButton?.addEventListener('click', () => {
            const isHidden = mainMenu.classList.toggle('hidden');
            mobileMenuButton.setAttribute('aria-expanded', String(!isHidden));
        });

        profileMenuButton?.addEventListener('click', () => {
            const isHidden = profileMenu.classList.toggle('hidden');
            profileMenuButton.setAttribute('aria-expanded', String(!isHidden));
        });

        document.addEventListener('click', (event) => {
            if (profileMenu && profileMenuButton && !profileMenuButton.contains(event.target) && !profileMenu.contains(event.target)) {
                profileMenu.classList.add('hidden');
                profileMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
    </script>
</body>
</html>