<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso interno | RESMAP</title>@vite('resources/css/app.css')<link rel="stylesheet" href="{{ asset('css/global-controls.css') }}"><script defer src="{{ asset('js/notifications.js') }}"></script>
<style>
    .login-heading::after {
        content: "";
        display: block;
        width: 5rem;
        height: 3px;
        margin: .6rem auto 0;
        border-radius: 9999px;
        background: #d52b31;
    }
</style>
</head>
<body class="flex min-h-screen items-center justify-center bg-[#10191d] bg-cover bg-center bg-fixed px-4 py-8 text-white" style="background-image: linear-gradient(rgba(8, 17, 22, .25), rgba(8, 17, 22, .35)), url('{{ asset('images/contacto-fondo.jpeg') }}');">
    <main class="w-full max-w-md rounded-[3rem] border border-[#d48880]/40 bg-[#20292c]/85 px-7 py-10 shadow-[0_20px_60px_rgba(0,0,0,.5)] backdrop-blur-md sm:rounded-[4rem] sm:px-12 sm:py-12">
        <div class="text-center">
            <p class="login-heading text-3xl font-black tracking-[.2em]">RESMAP</p>
            <a href="{{ route('home') }}" class="mt-3 inline-flex items-center rounded-full border border-white/60 px-5 py-2 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" style="border-radius: 9999px">← Volver al sitio</a>
            <h1 class="login-heading mt-6 text-3xl font-black">Acceso interno</h1>
        </div>
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
                    <label for="login" class="block text-sm font-bold text-white/90">Nombre de usuario</label>
                    <input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}" class="mt-2 w-full border border-[#b45e57]/60 bg-[#352d2d]/90 p-3 text-white outline-none transition focus:border-[#f0aaa2] focus:ring-2 focus:ring-[#f0aaa2]/50">
                </div>
                <div>
                    <label for="password" class="block text-sm font-bold text-white/90">Contraseña</label>
                    <div class="relative mt-2">
                        <input id="password" type="password" name="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="w-full border border-[#b45e57]/60 bg-[#352d2d]/90 p-3 pr-16 text-white outline-none transition focus:border-[#f0aaa2] focus:ring-2 focus:ring-[#f0aaa2]/50">
                        <button
                            id="toggle-password"
                            type="button"
                            aria-label="Mostrar contraseña"
                            aria-pressed="false"
                            class="absolute inset-y-1 right-1 inline-flex w-11 items-center justify-center bg-[#8f241d] text-white hover:bg-[#721d18] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                        >
                            <svg id="password-eye" aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="password-eye-off" aria-hidden="true" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>
                <button class="w-full border border-[#f08078]/50 bg-gradient-to-r from-[#9d0c0c] via-[#d52b31] to-[#9d0c0c] p-3 font-bold text-white shadow-[0_8px_24px_rgba(181,47,37,.35)] transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" style="border-radius: 9999px">Ingresar</button>
            </form>
            <a href="{{ route('password.request') }}" class="mt-5 block text-center text-sm font-bold text-[#ffaaa2] hover:text-white hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">¿Olvidaste tu contraseña?</a>
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
