<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso interno | RESMAP</title>@vite('resources/css/app.css')<link rel="stylesheet" href="{{ asset('css/global-controls.css') }}"><link rel="stylesheet" href="{{ asset('css/fondo-sistema.css') }}"><script defer src="{{ asset('js/notifications.js') }}"></script>
<style>
    .login-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, .72), rgba(255, 250, 248, .58));
        -webkit-backdrop-filter: blur(22px) saturate(125%);
        backdrop-filter: blur(22px) saturate(125%);
    }

    .login-heading::after {
        content: "";
        display: block;
        width: 5rem;
        height: 3px;
        margin: .6rem auto 0;
        border-radius: 9999px;
        background: #b52f25;
    }
</style>
</head>
<body class="resmap-background flex min-h-screen items-center justify-center px-4 py-8 text-[#241817]" style="--resmap-background-url: url('{{ $resmapBackgroundUrl }}')">
    <main class="login-card w-full max-w-md rounded-[3rem] border border-white/70 px-7 py-10 shadow-[0_20px_60px_rgba(36,24,23,.25)] sm:rounded-[4rem] sm:px-12 sm:py-12">
        <div class="text-center">
            <img src="{{ $resmapLogoUrl }}" alt="RESMAP" class="mx-auto h-28 w-auto object-contain">
            <a href="{{ route('home') }}" class="mt-3 inline-flex items-center rounded-full border border-[#d9aaa3] px-5 py-2 text-sm font-bold text-[#8f241d] transition hover:bg-[#fffaf9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]" style="border-radius: 9999px">← Volver al sitio</a>
            <h1 class="login-heading mt-6 text-3xl font-black">Acceso interno</h1>
        </div>
        @if(session('status'))<div class="mt-6 rounded-lg border-l-4 border-green-600 bg-green-50 p-4 text-sm text-green-900" role="status" data-auto-dismiss>{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="mt-6 rounded-lg border-l-4 border-red-500 bg-red-50 p-4 text-sm text-red-800" role="alert" aria-live="assertive">
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
                    <label for="login" class="block text-sm font-bold text-[#241817]">Nombre de usuario</label>
                    <input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}" class="mt-2 w-full border border-[#b45e57]/60 bg-white p-3 text-[#241817] outline-none transition focus:border-[#b52f25] focus:ring-2 focus:ring-[#f0aaa2]/50">
                </div>
                <div>
                    <label for="password" class="block text-sm font-bold text-[#241817]">Contraseña</label>
                    <div class="relative mt-2">
                        <input id="password" type="password" name="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="w-full border border-[#b45e57]/60 bg-white p-3 pr-16 text-[#241817] outline-none transition focus:border-[#b52f25] focus:ring-2 focus:ring-[#f0aaa2]/50">
                        <button
                            id="toggle-password"
                            type="button"
                            aria-label="Mostrar contraseña"
                            aria-pressed="false"
                            class="absolute inset-y-1 right-1 inline-flex w-11 items-center justify-center bg-transparent text-[#8f241d] hover:text-[#b52f25] focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-[#8f241d]"
                        >
                            <svg id="password-eye" aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="password-eye-off" aria-hidden="true" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>
                <button class="w-full border border-[#b52f25] bg-[#b52f25] p-3 font-bold text-white shadow-[0_8px_22px_rgba(181,47,37,.18)] transition hover:border-[#c7382f] hover:bg-[#c7382f] hover:shadow-[0_10px_28px_rgba(181,47,37,.32)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25]" style="border-radius: 9999px">Ingresar</button>
            </form>
            <a href="{{ route('password.request') }}" class="mt-5 block text-center text-sm font-bold text-[#8f241d] hover:text-[#721d18] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#8f241d]">¿Olvidaste tu contraseña?</a>
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
