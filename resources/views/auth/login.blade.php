<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso interno | RESMAP</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="flex min-h-screen items-center justify-center bg-[#8f241d] px-6 text-[#241817]">
    <main class="grid w-full max-w-4xl overflow-hidden bg-white shadow-2xl md:grid-cols-2">
        <div class="hidden min-h-130 bg-[linear-gradient(135deg,rgba(143,36,29,.9),rgba(55,16,13,.78)),url('https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=1200&q=80')] bg-cover bg-center p-10 text-white md:flex md:flex-col md:justify-between"><span class="text-xl font-black tracking-[.2em]">RESMAP</span><div><p class="text-sm font-bold uppercase tracking-[.25em] text-[#ffd5c9]">Centro de operaciones</p><h1 class="mt-3 text-4xl font-black leading-tight">Todo el trabajo, en un solo lugar.</h1></div></div>
        <div class="p-8 sm:p-12"><a href="{{ route('home') }}" class="text-sm font-bold text-[#9f2f25]">← Volver al sitio</a><h2 class="mt-12 text-3xl font-black">Acceso interno</h2><p class="mt-2 text-gray-500">Ingresa con tu usuario o correo.</p>
            @if($errors->any())<div class="mt-6 border-l-4 border-red-500 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
                <div><label for="login" class="block text-sm font-bold">Usuario o correo</label><input id="login" name="login" value="{{ old('login') }}" required autofocus class="mt-2 w-full border border-gray-300 p-3 outline-none focus:border-[#b52f25]"></div>
                <div><label for="password" class="block text-sm font-bold">Contraseña</label><input id="password" type="password" name="password" required class="mt-2 w-full border border-gray-300 p-3 outline-none focus:border-[#b52f25]"></div>
                <button class="w-full bg-[#b52f25] p-3 font-bold text-white transition hover:bg-[#8f241d]">Ingresar</button>
                <a href="{{ route('password.request') }}" class="block text-center text-sm font-bold text-[#9f2f25]">¿Olvidaste tu contraseña?</a>
            </form>
        </div>
    </main>
</body>
</html>
