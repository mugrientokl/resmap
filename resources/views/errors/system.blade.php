<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} · RESMAP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#f7f3f0] font-sans text-[#241817]">
    <main class="flex min-h-screen items-center justify-center px-5 py-12">
        <section class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <header class="flex items-center justify-between bg-[#8f241d] px-8 py-6">
                <a href="{{ route('home') }}" aria-label="Ir al inicio de RESMAP">
                    <img src="{{ asset('images/resmap sin fondo.png') }}" alt="RESMAP" class="h-14 w-auto">
                </a>
                <span class="rounded-full bg-white/15 px-4 py-2 text-sm font-bold tracking-widest text-white">ERROR {{ $status }}</span>
            </header>
            <div class="px-8 py-10 sm:px-12">
                <p class="text-sm font-black uppercase tracking-[.22em] text-[#b52f25]">Atención RESMAP</p>
                <h1 class="mt-3 text-3xl font-black sm:text-4xl">{{ $title }}</h1>
                <p class="mt-4 max-w-xl leading-7 text-gray-600">{{ $message }}</p>
                @if($status === 410)
                    <div class="mt-6 rounded-lg border border-[#ead8d5] bg-[#fffaf9] p-5 text-sm leading-6 text-gray-700">
                        Por seguridad, cada enlace de respuesta se utiliza una sola vez. Si ya respondiste la cotización, tu respuesta quedó registrada. Si aún necesitas responderla, solicita un nuevo enlace a nuestro equipo.
                    </div>
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('home') }}" class="rounded-md bg-[#b52f25] px-5 py-3 font-bold text-white transition hover:bg-[#8f241d]">Ir al inicio</a>
                    <button type="button" onclick="window.history.back()" class="rounded-md border border-[#d9aaa3] px-5 py-3 font-bold text-[#8f241d] transition hover:bg-[#fffaf9]">Volver a la página anterior</button>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
