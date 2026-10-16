<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Recuperar contraseña | RESMAP</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="flex min-h-screen items-center justify-center bg-[#8f241d] px-6 text-[#241817]">
<main class="w-full max-w-md bg-white p-8 shadow-2xl sm:p-12">
    <a href="{{ route('login') }}" class="text-sm font-bold text-[#9f2f25]">← Volver al acceso</a>
    <h1 class="mt-10 text-3xl font-black">Recuperar contraseña</h1>
    <p class="mt-2 text-gray-500">Enviaremos un enlace al correo registrado.</p>
    @if (session('status'))<div class="mt-6 bg-[#f7e8e6] p-4 text-sm text-[#8f241d]">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mt-6 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">@csrf
        <div><label for="email" class="block text-sm font-bold">Correo</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-2 w-full border border-gray-300 p-3"></div>
        <button class="w-full bg-[#b52f25] p-3 font-bold text-white">Enviar enlace</button>
    </form>
</main>
</body>
</html>
