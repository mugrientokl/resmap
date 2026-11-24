<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso interno | RESMAP</title>@vite('resources/css/app.css')<link rel="stylesheet" href="{{ asset('css/global-controls.css') }}"><script defer src="{{ asset('js/notifications.js') }}"></script></head>
<body class="flex min-h-screen items-center justify-center bg-[#8f241d] px-6 py-8 text-[#241817]">
    <main class="grid w-full max-w-4xl overflow-hidden bg-white shadow-2xl md:grid-cols-2">
        <div class="hidden min-h-130 bg-[linear-gradient(135deg,rgba(143,36,29,.9),rgba(55,16,13,.78)),url('https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=1200&q=80')] bg-cover bg-center p-10 text-white md:flex md:flex-col md:justify-between"><span class="text-xl font-black tracking-[.2em]">RESMAP</span></div>
        <div class="p-8 sm:p-12"><a href="{{ route('home') }}" class="text-sm font-bold text-[#9f2f25]">← Volver al sitio</a><h1 class="mt-12 text-3xl font-black">Acceso interno</h1>
        @if(session('status'))<div class="mt-6 border-l-4 border-green-600 bg-green-50 p-4 text-sm text-green-900" role="status" data-auto-dismiss>{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="mt-6 border-l-4 border-red-500 bg-red-50 p-4 text-sm text-red-800" role="alert" aria-live="assertive">
                <p class="font-bold">No pudimos iniciar sesión.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
            <form id="password-login-form" method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
                <div>
                    <label for="login" class="block text-sm font-bold">Nombre de usuario</label>
                    <input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}" class="mt-2 w-full border border-[#dccac6] bg-[#fffcfb] p-3 outline-none transition focus:border-[#b52f25] focus:ring-2 focus:ring-[#f3d4ce]">
                </div>
                <div>
                    <label for="password" class="block text-sm font-bold">Contraseña</label>
                    <div class="relative mt-2">
                        <input id="password" type="password" name="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="w-full border border-[#dccac6] bg-[#fffcfb] p-3 pr-12 outline-none transition focus:border-[#b52f25] focus:ring-2 focus:ring-[#f3d4ce]">
                        <button
                            id="toggle-password"
                            type="button"
                            aria-label="Mostrar contraseña"
                            aria-pressed="false"
                            class="absolute inset-y-0 right-0 inline-flex items-center gap-1 px-3 text-sm font-bold text-[#9f2f25] hover:text-[#721d18] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#b52f25]"
                        >
                            <svg id="password-eye" aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="password-eye-off" aria-hidden="true" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>
                <button class="w-full bg-[#b52f25] p-3 font-bold text-white shadow-md transition hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">Ingresar</button>
            </form>
            <a href="{{ route('password.request') }}" class="mt-5 block text-center text-sm font-bold text-[#9f2f25] hover:text-[#721d18] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#b52f25]">¿Olvidaste tu contraseña?</a>
        </div>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('toggle-password');
        const passwordEye = document.getElementById('password-eye');
        const passwordEyeOff = document.getElementById('password-eye-off');

        togglePassword?.addEventListener('click', () => {
            const mostrar = passwordInput.type === 'password';
            passwordInput.type = mostrar ? 'text' : 'password';
            togglePassword.setAttribute('aria-pressed', String(mostrar));
            togglePassword.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
            passwordEye.classList.toggle('hidden', mostrar);
            passwordEyeOff.classList.toggle('hidden', !mostrar);
            passwordInput.focus();
        });
    </script>
</body>
</html>
