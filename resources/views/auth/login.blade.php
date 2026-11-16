<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso interno | RESMAP</title>@vite(['resources/css/app.css', 'resources/js/face-recognition.js'])<link rel="stylesheet" href="{{ asset('css/global-controls.css') }}"><script defer src="{{ asset('js/notifications.js') }}"></script></head>
<body class="flex min-h-screen justify-center bg-[linear-gradient(135deg,#f7f3f0_0%,#f2ded9_100%)] px-4 py-8 font-sans text-[#241817] sm:px-6">
    <main class="my-auto grid w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-[0_24px_70px_rgba(80,24,18,0.18)] md:grid-cols-2">
        <div class="hidden min-h-150 bg-[linear-gradient(160deg,rgba(75,21,18,.86),rgba(143,36,29,.82)),url('https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=1200&q=80')] bg-cover bg-center p-10 text-white md:flex md:flex-col md:justify-between lg:p-12">
            <div class="w-fit rounded-2xl bg-[#fff8f4] px-5 py-3 shadow-lg">
                <img src="{{ asset('images/resmap sin fondo.png') }}" alt="RESMAP" class="h-20 w-auto object-contain">
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-[.25em] text-[#ffd5c9]">Centro de operaciones</p>
                <p class="mt-4 text-4xl font-black leading-tight">Todo el trabajo,<br>en un solo lugar.</p>
                <p class="mt-4 max-w-xs text-base leading-relaxed text-[#ffe9e2]">Gestiona tu trabajo diario desde un espacio pensado para tu equipo.</p>
            </div>
        </div>
        <div class="p-6 sm:p-10 lg:p-12">
            <div class="mb-8 flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-bold text-[#9f2f25] hover:text-[#721d18] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#b52f25]">← Volver al sitio</a>
                <img src="{{ asset('images/resmap sin fondo.png') }}" alt="RESMAP" class="h-14 w-auto object-contain md:hidden">
            </div>
            <p class="text-xs font-bold uppercase tracking-[.2em] text-[#9f2f25]">Bienvenido a RESMAP</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Acceso interno</h1>
            <p class="mt-3 text-base leading-relaxed text-gray-600">Ingresa con tu usuario o correo.</p>
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
            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5" data-face-mode="login" data-models-url="{{ asset('models') }}" data-face-login-url="{{ route('login.face') }}">@csrf
                <div><label for="login" class="block text-sm font-bold">Usuario o correo</label><input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}" class="mt-2 w-full border border-[#dccac6] bg-[#fffcfb] p-3 outline-none transition focus:border-[#b52f25] focus:ring-2 focus:ring-[#f3d4ce]"></div>
                <div>
                    <label for="password" class="block text-sm font-bold">Contraseña</label>
                    <div class="relative mt-2">
                        <input id="password" type="password" name="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="w-full border border-[#dccac6] bg-[#fffcfb] p-3 pr-24 outline-none transition focus:border-[#b52f25] focus:ring-2 focus:ring-[#f3d4ce]">
                        <button
                            id="toggle-password"
                            type="button"
                            aria-label="Mostrar contraseña"
                            aria-pressed="false"
                            class="absolute inset-y-0 right-0 inline-flex items-center gap-1 px-3 text-sm font-bold text-[#9f2f25] hover:text-[#721d18] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#b52f25]"
                        >
                            <svg id="password-eye-open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.5-7 9.75-7 9.75 7 9.75 7-3.5 7-9.75 7-9.75-7-9.75-7Z"/>
                                <circle cx="12" cy="12" r="3" stroke-width="2"/>
                            </svg>
                            <svg id="password-eye-closed" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c6.25 0 9.75 7 9.75 7a15.7 15.7 0 0 1-3.1 3.9M6.2 6.2C3.6 8 2.25 12 2.25 12s3.5 7 9.75 7c1 0 1.9-.2 2.8-.5"/>
                            </svg>
                            <span id="toggle-password-label">Mostrar</span>
                        </button>
                    </div>
                </div>
                <button class="w-full bg-[#b52f25] p-3 font-bold text-white shadow-md transition hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">Ingresar</button>
                <button type="button" data-face-open class="flex w-full items-center justify-center gap-2 border border-[#d6aaa3] bg-[#fff9f7] p-3 font-bold text-[#8f241d] transition hover:bg-[#f7e8e6] focus:outline-none focus:ring-2 focus:ring-[#b52f25]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7.5h3l1.5-2h7L17 7.5h3v11H4z"/><circle cx="12" cy="13" r="3.5" stroke-width="1.8"/></svg>
                    Ingresar con reconocimiento facial
                </button>
                <p data-face-status class="min-h-5 text-center text-sm leading-5 text-gray-600" role="status" aria-live="polite">También se verificará tu contraseña. El rostro no inicia sesión por sí solo.</p>
                <a href="{{ route('password.request') }}" class="block text-center text-sm font-bold text-[#9f2f25] hover:text-[#721d18] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#b52f25]">¿Olvidaste tu contraseña?</a>
            </form>
        </div>
    </main>
    @include('components.face-camera')
    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('toggle-password');
        const togglePasswordLabel = document.getElementById('toggle-password-label');
        const passwordEyeOpen = document.getElementById('password-eye-open');
        const passwordEyeClosed = document.getElementById('password-eye-closed');

        togglePassword.addEventListener('click', () => {
            const mostrar = passwordInput.type === 'password';
            passwordInput.type = mostrar ? 'text' : 'password';
            togglePassword.setAttribute('aria-pressed', String(mostrar));
            togglePassword.setAttribute(
                'aria-label',
                mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
            );
            togglePasswordLabel.textContent = mostrar ? 'Ocultar' : 'Mostrar';
            passwordEyeOpen.classList.toggle('hidden', mostrar);
            passwordEyeClosed.classList.toggle('hidden', !mostrar);
            passwordInput.focus();
        });
    </script>
</body>
</html>
