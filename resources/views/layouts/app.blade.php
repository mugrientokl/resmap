<!DOCTYPE html>
@php($isHome = request()->routeIs('home'))
@php($posicionNavegacion = auth()->check() ? (\Illuminate\Support\Facades\DB::table('configuraciones')->where('clave', 'posicion_navegacion')->value('valor') ?? 'superior') : 'superior')
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESMAP - Sistema de Gestión</title>
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('css/global-controls.css') }}">
    <script defer src="{{ asset('js/notifications.js') }}"></script>
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
        .brand-caption { display: none; }
        .nav-icon { display: none; }

        #home-navigation { transition: background-color .3s ease, box-shadow .3s ease; }
        #home-navigation.is-scrolled { background-color: #8f241d; box-shadow: 0 4px 16px rgba(36, 24, 23, .2); }

        body.navegacion-superior #main-navigation {
            background: linear-gradient(110deg, #5c1c19, #8f241d);
            box-shadow: 0 5px 18px rgba(55, 16, 13, .2);
        }
        body.navegacion-superior > nav > div {
            max-width: none !important;
            min-height: 76px;
            gap: 1rem;
            padding: .55rem clamp(1rem, 2vw, 2rem);
        }
        body.navegacion-superior .brand-link {
            flex: none;
            padding: .3rem .7rem;
            border: 1px solid rgba(255, 242, 229, .6);
            border-radius: .75rem;
            background: linear-gradient(145deg, #fff8f1, #eadbd2);
            box-shadow: 0 4px 12px rgba(49, 12, 9, .18);
        }
        body.navegacion-superior .brand-logo {
            width: 7rem;
            height: 2.6rem;
            max-width: none;
            filter: none;
        }
        body.navegacion-superior .nav-link {
            display: flex;
            align-items: center;
            gap: .45rem;
            min-height: 2.75rem;
            padding: .6rem .45rem;
            border: 1px solid transparent;
            border-radius: .65rem;
            color: #fff7f5;
            font-size: .9375rem;
            font-weight: 600;
            line-height: 1.25;
            transition: background-color .15s ease, border-color .15s ease;
        }
        body.navegacion-superior .nav-icon {
            display: block;
            width: 1.1rem;
            height: 1.1rem;
            flex: none;
            color: #ffd5c9;
        }
        body.navegacion-superior .nav-link:hover,
        body.navegacion-superior #profile-menu-button:hover {
            background-color: rgba(255, 255, 255, .1);
        }
        body.navegacion-superior .nav-link[aria-current="page"] {
            border-color: #f5d6cf;
            background-color: #fff4f0;
            color: #721d18;
            box-shadow: 0 3px 10px rgba(49, 12, 9, .16);
        }
        body.navegacion-superior .nav-link[aria-current="page"] .nav-icon { color: #9f2f25; }
        body.navegacion-superior .brand-link:focus-visible,
        body.navegacion-superior .nav-link:focus-visible,
        body.navegacion-superior #mobile-menu-button:focus-visible,
        body.navegacion-superior #profile-menu-button:focus-visible {
            outline: 3px solid #ffd5c9;
            outline-offset: 2px;
        }
        body.navegacion-superior #profile-menu-button {
            min-height: 2.75rem;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: .65rem;
            background-color: rgba(255, 255, 255, .07);
            font-size: .9375rem;
        }
        body.navegacion-superior #profile-menu-button > span:first-child { flex: none; }
        body.navegacion-superior #profile-menu-button > span:nth-child(2) {
            overflow: hidden;
            max-width: 7rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        body.navegacion-superior #profile-menu-button > svg { flex: none; }
        body.navegacion-superior #profile-menu {
            border: 1px solid #e8c8c3;
            border-radius: .65rem;
            font-size: .9375rem;
        }
        @media not all and (min-width: 1440px) {
            body.navegacion-superior #mobile-menu-button {
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 2.75rem;
                min-height: 2.75rem;
                border: 1px solid rgba(255, 255, 255, .25);
                background: rgba(255, 255, 255, .08);
            }
            body.navegacion-superior #main-menu {
                position: absolute;
                top: 100%;
                right: 0;
                left: 0;
                display: flex;
                max-height: calc(100dvh - 76px);
                overflow-y: auto;
                align-items: stretch;
                flex-direction: column;
                gap: .25rem;
                padding: .75rem 1rem 1rem;
                background: #75231f;
                box-shadow: 0 8px 18px rgba(36, 24, 23, .2);
            }
            body.navegacion-superior #main-menu.hidden { display: none; }
            body.navegacion-superior .nav-badge { margin-left: auto; }
            body.navegacion-superior .profile-section {
                margin-top: .5rem;
                padding-top: .75rem;
                border-top: 1px solid rgba(255, 255, 255, .25);
            }
            body.navegacion-superior #profile-menu-button > span:nth-child(2) {
                flex: 1;
                max-width: none;
            }
            body.navegacion-superior #profile-menu { position: static; margin-top: .35rem; }
        }
        @media (min-width: 1440px) {
            body.navegacion-superior #main-menu { gap: .15rem; }
            body.navegacion-superior #main-menu > .nav-link { flex: none; white-space: nowrap; }
            body.navegacion-superior .profile-section { flex: none; margin-left: .35rem; }
        }

        @media (max-width: 767px) {
            #main-menu { max-height: calc(100vh - 76px); overflow-y: auto; }
            body.public-navigation.navegacion-izquierda { padding-top: 0; padding-left: 5rem; }
            body.public-navigation.navegacion-izquierda > nav {
                right: auto;
                bottom: 0;
                width: 5rem;
                overflow-y: auto;
                background-color: #8f241d;
                box-shadow: 4px 0 16px rgba(36, 24, 23, .2);
            }
            body.public-navigation.navegacion-izquierda > nav > div {
                max-width: none !important;
                align-items: stretch;
                flex-direction: column;
                padding: .5rem;
            }
            body.public-navigation.navegacion-izquierda .brand-link {
                justify-content: center;
                padding: .3rem;
                border-radius: .6rem;
                background: #fff4f0;
            }
            body.public-navigation.navegacion-izquierda .brand-logo { width: 100%; height: auto; filter: none; }
            body.public-navigation.navegacion-izquierda #public-menu {
                align-items: stretch;
                flex-direction: column;
                gap: .35rem;
                margin-top: .75rem;
            }
            body.public-navigation.navegacion-izquierda .nav-link {
                display: flex;
                min-height: 3.75rem;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                gap: .2rem;
                padding: .4rem .1rem;
                border: 1px solid transparent;
                border-radius: .6rem;
                color: #fff7f5;
                font-size: .625rem;
                font-weight: 600;
                line-height: 1.15;
                text-align: center;
            }
            body.public-navigation.navegacion-izquierda .nav-icon { display: block; width: 1.25rem; height: 1.25rem; flex: none; }
            body.public-navigation.navegacion-izquierda .nav-link:hover { background-color: rgba(255, 255, 255, .1); }
            body.public-navigation.navegacion-izquierda .nav-link[aria-current="page"] { background-color: #fff4f0; color: #721d18; }
            body.public-navigation.navegacion-izquierda .nav-link:focus-visible,
            body.public-navigation.navegacion-izquierda .brand-link:focus-visible { outline: 3px solid #ffd5c9; outline-offset: 2px; }
            body.public-navigation.navegacion-izquierda #barra-carrito { left: 5rem; }
        }

        @media (min-width: 768px) {
            body.navegacion-izquierda { padding-top: 0; padding-left: 16rem; }
            body.navegacion-izquierda > nav {
                right: auto;
                bottom: 0;
                width: 16rem;
                overflow-y: auto;
                background-color: #8f241d;
                box-shadow: 4px 0 16px rgba(36, 24, 23, .2);
            }
            body.navegacion-izquierda > nav > div {
                max-width: none !important;
                align-items: stretch;
                flex-direction: column;
                padding: 1rem;
            }
            body.navegacion-izquierda .brand-link {
                flex-direction: column;
                justify-content: center;
                padding: .8rem 1rem .75rem;
                border: 1px solid rgba(255, 255, 255, .7);
                border-radius: .75rem;
                background: linear-gradient(145deg, #fff, #f8ece8);
                box-shadow: 0 6px 16px rgba(49, 12, 9, .18);
            }
            body.navegacion-izquierda .brand-link:focus-visible {
                outline: 3px solid #ffd5c9;
                outline-offset: 3px;
            }
            body.navegacion-izquierda .brand-logo {
                width: 100%;
                height: auto;
                max-width: 190px;
                filter: none;
            }
            body.navegacion-izquierda .brand-caption {
                display: block;
                margin-top: .2rem;
                color: #8f241d;
                font-size: .6rem;
                font-weight: 800;
                letter-spacing: .14em;
                text-align: center;
                text-transform: uppercase;
            }
            body.navegacion-izquierda #main-menu,
            body.navegacion-izquierda #public-menu {
                align-items: stretch;
                flex-direction: column;
                gap: .25rem;
                margin-top: 1rem;
            }
            body.navegacion-izquierda .nav-link {
                display: flex;
                align-items: center;
                gap: .75rem;
                min-height: 2.75rem;
                padding: .6rem .75rem;
                border: 1px solid transparent;
                border-radius: .65rem;
                color: #fff7f5;
                font-size: .9375rem;
                font-weight: 600;
                line-height: 1.35;
                transition: background-color .15s ease, border-color .15s ease, color .15s ease;
            }
            body.navegacion-izquierda .nav-icon {
                display: block;
                width: 1.2rem;
                height: 1.2rem;
                flex: none;
                color: #ffd5c9;
            }
            body.navegacion-izquierda .nav-link:hover {
                border-color: rgba(255, 255, 255, .16);
                background-color: rgba(255, 255, 255, .1);
                color: #fff;
            }
            body.navegacion-izquierda .nav-link[aria-current="page"] {
                border-color: #f5d6cf;
                background-color: #fff4f0;
                color: #721d18;
                box-shadow: 0 3px 10px rgba(49, 12, 9, .16);
            }
            body.navegacion-izquierda .nav-link[aria-current="page"] .nav-icon { color: #9f2f25; }
            body.navegacion-izquierda .nav-link:focus-visible,
            body.navegacion-izquierda #profile-menu-button:focus-visible {
                outline: 3px solid #ffd5c9;
                outline-offset: 2px;
            }
            body.navegacion-izquierda .nav-badge { margin-left: auto; }
            body.navegacion-izquierda .profile-section {
                margin-top: .75rem;
                padding-top: .75rem;
                border-top: 1px solid rgba(255, 255, 255, .25);
            }
            body.navegacion-izquierda #profile-menu-button {
                min-height: 3.25rem;
                border: 1px solid rgba(255, 255, 255, .18);
                border-radius: .65rem;
                background-color: rgba(255, 255, 255, .07);
                font-size: .9375rem;
            }
            body.navegacion-izquierda #profile-menu-button:hover { background-color: rgba(255, 255, 255, .14); }
            body.navegacion-izquierda #profile-menu-button > svg { margin-left: auto; flex: none; }
            body.navegacion-izquierda #profile-menu {
                position: static;
                margin-top: .25rem;
                font-size: .9375rem;
            }
            body.public-navigation.navegacion-izquierda #barra-carrito { left: 16rem; }
        }

        body.public-navigation > nav {
            background-image: linear-gradient(165deg, #5c1c19 0%, #75231f 55%, #8f3027 100%);
            box-shadow: 4px 0 20px rgba(55, 16, 13, .15);
        }
        body.public-navigation .brand-link {
            border-color: rgba(255, 242, 229, .45);
            background: linear-gradient(145deg, #fff8f1, #eadbd2);
            box-shadow: 0 6px 16px rgba(49, 12, 9, .12);
        }
        body.public-navigation .nav-link[aria-current="page"] {
            border-color: rgba(246, 185, 65, .35);
            background-color: rgba(255, 244, 235, .13);
            color: #fff;
            box-shadow: inset 3px 0 #f6b941;
        }
        body.public-navigation .nav-link[aria-current="page"] .nav-icon { color: #f6b941; }
        body.public-navigation.navegacion-izquierda .hero-bleed::after {
            position: absolute;
            inset: 0 auto 0 0;
            z-index: 1;
            width: clamp(5rem, 18vw, 18rem);
            background: linear-gradient(90deg, rgba(92, 28, 25, .6), transparent);
            content: "";
            pointer-events: none;
        }
        @media (min-width: 768px) {
            body.public-navigation.navegacion-izquierda #public-menu::before {
                padding: .5rem .75rem .4rem;
                color: #e8bbb0;
                content: "EXPLORA RESMAP";
                font-size: .65rem;
                font-weight: 800;
                letter-spacing: .14em;
            }
        }
        #public-menu-toggle { display: none; }
        #public-navigation-position:not([hidden]) {
            display: flex;
            align-items: center;
            gap: .6rem;
            min-height: 2.75rem;
            padding: .6rem .75rem;
            border: 1px solid rgba(255, 255, 255, .3);
            border-radius: .65rem;
            background: rgba(255, 255, 255, .08);
            color: #fff7f5;
            font-size: .875rem;
            font-weight: 700;
            line-height: 1.2;
        }
        #public-navigation-position:hover,
        #public-menu-toggle:hover { background: rgba(255, 255, 255, .16); }
        #public-navigation-position:focus-visible,
        #public-menu-toggle:focus-visible { outline: 3px solid #ffd5c9; outline-offset: 2px; }
        #public-navigation-position svg { width: 1.2rem; height: 1.2rem; flex: none; color: #ffd5c9; }
        body.public-navigation.navegacion-izquierda #public-navigation-position { margin-top: .75rem; }
        body.public-navigation.navegacion-superior > nav { box-shadow: 0 5px 18px rgba(55, 16, 13, .2); }
        body.public-navigation.navegacion-superior #public-menu { gap: .25rem; }
        body.public-navigation.navegacion-superior #public-menu > * { white-space: nowrap; }
        @media (max-width: 767px) {
            body.public-navigation.navegacion-izquierda #public-navigation-position:not([hidden]) {
                justify-content: center;
                flex-direction: column;
                gap: .2rem;
                min-height: 3.75rem;
                padding: .4rem .1rem;
                font-size: .625rem;
                text-align: center;
            }
            body.public-navigation.navegacion-superior #public-menu-toggle:not([hidden]) {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 2.75rem;
                height: 2.75rem;
                border: 1px solid rgba(255, 255, 255, .3);
                border-radius: .65rem;
                background: rgba(255, 255, 255, .08);
            }
            body.public-navigation.navegacion-superior #public-menu {
                position: absolute;
                top: 100%;
                right: 0;
                left: 0;
                display: none;
                max-height: calc(100dvh - 76px);
                overflow-y: auto;
                align-items: stretch;
                flex-direction: column;
                padding: .75rem 1rem 1rem;
                background: #75231f;
                box-shadow: 0 8px 18px rgba(36, 24, 23, .2);
            }
            body.public-navigation.navegacion-superior #public-menu.is-open { display: flex; }
            body.public-navigation.navegacion-superior #public-navigation-position { margin-top: .5rem; }
        }
    </style>
</head>
<body class="bg-[#f7f3f0] font-sans antialiased text-[#241817] {{ $isHome ? 'homepage' : '' }} {{ auth()->guest() ? 'navegacion-izquierda public-navigation' : ($posicionNavegacion === 'izquierda' ? 'navegacion-izquierda' : 'navegacion-superior') }}">
    @guest
        <script>
            try {
                if (window.localStorage.getItem('resmap-menu-publico') === 'superior') {
                    document.body.classList.replace('navegacion-izquierda', 'navegacion-superior');
                }
            } catch (error) {
                console.warn('No se pudo leer la posición del menú público.', error);
            }
        </script>
    @endguest
    <nav id="{{ $isHome ? 'home-navigation' : 'main-navigation' }}" class="fixed inset-x-0 top-0 z-40 text-white {{ $isHome ? 'bg-transparent' : 'bg-[#8f241d] shadow-lg' }}">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="brand-link flex items-center" aria-label="Ir al inicio de Resmap">
                <img src="{{ asset('images/resmap sin fondo.png') }}" alt="Resmap" class="brand-logo h-12 w-auto max-w-45 object-contain">
                <span class="brand-caption" aria-hidden="true">Repuestos y maquinaria</span>
            </a>
            @auth
                @php($unreadNotifications = auth()->user()->unreadNotifications()->whereNull('deleted_at')->count())
                @php($pendingRequests = \App\Models\SolicitudWeb::where('tipo_solicitud', '!=', 'servicio')->where(function ($query) { $query->whereDoesntHave('cotizacion')->orWhereHas('cotizacion', fn ($query) => $query->whereIn('estado', ['pendiente_revision', 'borrador'])); })->count())
                <button type="button" id="mobile-menu-button" class="rounded-md p-2 hover:bg-[#721d18] md:hidden" aria-controls="main-menu" aria-expanded="false" aria-label="Abrir menú">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            @endauth
            @auth
                <div id="main-menu" class="absolute left-0 right-0 top-full hidden bg-[#8f241d] px-4 pb-4 shadow-lg md:static md:flex md:items-center md:gap-1 md:bg-transparent md:p-0 md:shadow-none xl:gap-4">
                    <a href="{{ route('dashboard') }}" class="nav-link block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('dashboard', 'dashboard.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10Zm0 8h8v-5H3v5Zm11 0h7V11h-7v10Zm0-18v5h7V3h-7Z"/></svg><span>Dashboard</span></a>
                    <a href="{{ route('productos.index') }}" class="nav-link block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('productos.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7m-9 4v10"/></svg><span>Inventario</span></a>
                    <a href="{{ route('pos.index') }}" class="nav-link block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('pos.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2 11h11l3-8H6m2 12h.01M17 19h.01"/></svg><span>POS</span></a>
                    <a href="{{ route('catalogo.index') }}" class="nav-link block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('catalogo.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h6a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H4V5Zm16 0h-6a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h6V5Z"/></svg><span>Catálogo</span></a>
                    <a href="{{ route('servicios.index') }}" class="nav-link block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('servicios.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.5a5 5 0 0 0-6.3 6.3l-5 5a2 2 0 0 0 2.8 2.8l5-5a5 5 0 0 0 6.3-6.3l-3.1 3.1-2.6-2.6 2.9-3.3Z"/></svg><span>Servicios</span></a>
                    <a href="{{ route('solicitudes.index') }}" class="nav-link block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('solicitudes.*', 'cotizaciones.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h8l4 4v14H6V3Zm8 0v4h4M9 11h6M9 15h6"/></svg><span>Cotizaciones / Servicios</span> @if($pendingRequests)<span class="nav-badge ml-1 rounded-full bg-[#ffd5c9] px-2 py-0.5 text-xs text-[#8f241d]">{{ $pendingRequests }}</span>@endif</a>
                    @if(auth()->user()->rol === 'Administrador' || auth()->user()->rol === 'Vendedor')
                        <a href="{{ route('clientes.index') }}" class="nav-link block rounded px-3 py-2 text-sm hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('clientes.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-5a4 4 0 0 1 0 7m2 8v-2a4 4 0 0 0-2-3.46"/></svg><span>Clientes</span></a>
                    @endif
                    @if(auth()->user()->rol === 'Administrador')
                        <a href="{{ route('administracion.index') }}" class="nav-link block rounded px-3 py-2 text-sm font-bold hover:bg-[#721d18] hover:text-[#ffd5c9]" @if(request()->routeIs('administracion.*', 'categorias.*', 'proveedores.*', 'usuarios.*', 'backups.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7ZM19 12l2-1-2-3-2 .5-1.5-1L15 5h-3l-.5 2.5-1.5 1L8 8l-2 3 2 1v2l-2 1 2 3 2-.5 1.5 1L12 21h3l.5-2.5 1.5-1 2 .5 2-3-2-1v-2Z"/></svg><span>Administración</span></a>
                    @endif
                    <div class="profile-section relative mt-2 border-t border-[#b5534b] pt-2 md:mt-0 md:border-0 md:pt-0">
                        <button type="button" id="profile-menu-button" class="flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm hover:bg-[#721d18]" aria-haspopup="true" aria-expanded="false">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#ffd5c9] font-bold text-[#8f241d]">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <span>{{ auth()->user()->name }}</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" /></svg>
                        </button>
                        <div id="profile-menu" class="absolute right-0 mt-1 hidden min-w-48 rounded-md bg-white py-1 text-sm text-gray-700 shadow-xl">
                            <a href="{{ route('perfil.show') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Mi perfil</a>
                            @if(auth()->user()->rol === 'Administrador')
                                <a href="{{ route('reportes.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Reportes</a>
                                <a href="{{ route('inventario.movimientos') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Movimientos de Inventario</a>
                                @can('ver-actividad')
                                    <a href="{{ route('actividad.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Registro de Actividad</a>
                                @endcan
                            @endif
                            <a href="{{ route('notificaciones.index') }}" class="block px-4 py-2 hover:bg-[#f7e8e6]">Avisos @if($unreadNotifications)<span class="ml-1 rounded-full bg-[#b52f25] px-2 py-0.5 text-xs text-white">{{ $unreadNotifications }}</span>@endif</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-red-700 hover:bg-red-50">Cerrar sesión</button></form>
                        </div>
                    </div>
                </div>
            @else
                <button type="button" id="public-menu-toggle" hidden aria-controls="public-menu" aria-expanded="false" aria-label="Abrir menú">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div id="public-menu" class="flex items-center gap-3 text-sm">
                    <a href="{{ route('home') }}" class="nav-link" @if($isHome) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7M5 9v11h14V9m-9 11v-6h4v6"/></svg><span>Inicio</span></a>
                    <a href="{{ route('catalogo.index') }}" class="nav-link" @if(request()->routeIs('catalogo.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h6a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H4V5Zm16 0h-6a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h6V5Z"/></svg><span>Catálogo</span></a>
                    <a href="{{ route('servicios.index') }}" class="nav-link" @if(request()->routeIs('servicios.*')) aria-current="page" @endif><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.5a5 5 0 0 0-6.3 6.3l-5 5a2 2 0 0 0 2.8 2.8l5-5a5 5 0 0 0 6.3-6.3l-3.1 3.1-2.6-2.6 2.9-3.3Z"/></svg><span>Servicios</span></a>
                    <a href="{{ route('login') }}" class="nav-link"><svg class="nav-icon" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h5v16h-5M10 8l4 4-4 4m4-4H3"/></svg><span>Acceso interno</span></a>
                    <button type="button" id="public-navigation-position" hidden aria-label="Cambiar menú a la parte superior">
                        <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM4 9h16M9 9v10" /></svg>
                        <span>Menú arriba</span>
                    </button>
                </div>
            @endauth
        </div>
    </nav>

    <main class="mx-auto min-h-[calc(100vh-140px)] py-6">
        @if (filled(session('success')) || filled(session('status')) || filled(session('warning')) || filled(session('error')) || filled(session('rejected')))
            <div id="flash-notifications" class="pointer-events-none fixed right-4 top-20 z-[60] flex w-[min(32rem,calc(100vw-2rem))] flex-col gap-3 sm:right-6" aria-live="polite" aria-relevant="additions">
                @foreach ([
                    'success' => ['Operación completada', 'border-green-600 bg-green-50 text-green-950 ring-green-200', 'status', '✓'],
                    'status' => ['Aviso', 'border-green-600 bg-green-50 text-green-950 ring-green-200', 'status', '✓'],
                    'warning' => ['Atención', 'border-amber-500 bg-amber-50 text-amber-950 ring-amber-200', 'alert', '!'],
                    'error' => ['No se pudo completar', 'border-red-600 bg-red-50 text-red-950 ring-red-200', 'alert', '!'],
                    'rejected' => ['Cotización rechazada', 'border-red-600 bg-red-50 text-red-950 ring-red-200', 'status', '!'],
                ] as $flashKey => [$flashTitle, $flashClasses, $flashRole, $flashIcon])
                    @if (filled(session($flashKey)) && ! ($flashKey === 'success' && request()->routeIs('catalogo.index')))
                        <div class="pointer-events-auto flex items-start gap-4 rounded-r-xl border-l-[6px] p-5 shadow-xl ring-1 {{ $flashClasses }}" role="{{ $flashRole }}" data-auto-dismiss>
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-2xl font-black shadow-sm" aria-hidden="true">{{ $flashIcon }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-black">{{ $flashTitle }}</p>
                                <p class="mt-1 break-words text-sm leading-6">{{ session($flashKey) }}</p>
                            </div>
                            <button type="button" class="-mr-2 -mt-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xl font-bold opacity-70 transition hover:bg-black/5 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current" aria-label="Cerrar notificación" data-dismiss-notification>×</button>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
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
            <section class="mx-auto mt-8 flex max-w-7xl flex-col items-start gap-5 border-t border-[#f0ddda] pt-6 sm:flex-row sm:items-center" aria-labelledby="instituto-title">
                <a href="https://admision.virginiogomez.cl/sedes/" target="_blank" rel="noopener noreferrer">
                    <img src="https://www.virginiogomez.cl/static/admision/Logo_IPVG.png" alt="Instituto Profesional Virginio Gómez" class="h-auto w-48 object-contain" loading="lazy">
                </a>
                <div>
                    <h2 id="instituto-title" class="font-bold text-[#241817]">Instituto Profesional Virginio Gómez · Sede Los Ángeles</h2>
                    <p class="mt-1">Ercilla 444, Los Ángeles, Biobío</p>
                    <a href="tel:+56432317764" class="mt-1 inline-block hover:text-[#9f2f25]">+56 43 231 7764</a>
                </div>
            </section>
        @endif
        <p class="mt-8 border-t border-[#f0ddda] pt-5 text-center">© RESMAP 2026. Todos Los Derechos Reservados.</p>
    </footer>
    @include('components.resmap-dialog')
    <script>
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mainMenu = document.getElementById('main-menu');
        const profileMenuButton = document.getElementById('profile-menu-button');
        const profileMenu = document.getElementById('profile-menu');
        const homeNavigation = document.getElementById('home-navigation');
        const heroBanner = document.querySelector('.hero-bleed');
        const publicMenu = document.getElementById('public-menu');
        const publicMenuToggle = document.getElementById('public-menu-toggle');
        const publicNavigationPosition = document.getElementById('public-navigation-position');

        if (publicNavigationPosition && publicMenuToggle && publicMenu) {
            const updatePublicNavigationControl = () => {
                const isTop = document.body.classList.contains('navegacion-superior');
                publicNavigationPosition.querySelector('span').textContent = isTop ? 'Menú lateral' : 'Menú arriba';
                publicNavigationPosition.setAttribute('aria-label', isTop ? 'Cambiar menú al lado izquierdo' : 'Cambiar menú a la parte superior');
            };

            updatePublicNavigationControl();
            publicNavigationPosition.hidden = false;
            publicMenuToggle.hidden = false;

            publicMenuToggle.addEventListener('click', () => {
                const isOpen = publicMenu.classList.toggle('is-open');
                publicMenuToggle.setAttribute('aria-expanded', String(isOpen));
                publicMenuToggle.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
            });

            publicNavigationPosition.addEventListener('click', () => {
                const isTop = document.body.classList.contains('navegacion-izquierda');
                document.body.classList.toggle('navegacion-superior', isTop);
                document.body.classList.toggle('navegacion-izquierda', !isTop);
                publicMenu.classList.toggle('is-open', isTop);
                publicMenuToggle.setAttribute('aria-expanded', String(isTop));
                publicMenuToggle.setAttribute('aria-label', isTop ? 'Cerrar menú' : 'Abrir menú');
                updatePublicNavigationControl();

                try {
                    window.localStorage.setItem('resmap-menu-publico', isTop ? 'superior' : 'izquierda');
                } catch (error) {
                    console.warn('No se pudo guardar la posición del menú público.', error);
                    window.resmapAlert('No se pudo guardar tu preferencia. El cambio solo se mantendrá en esta página.', 'warning');
                }
            });
        }

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