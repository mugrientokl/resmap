<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso interno | RESMAP</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="{{ asset('css/global-controls.css') }}"></head>
<body class="flex min-h-screen items-center justify-center bg-[#8f241d] px-6 text-[#241817]">
    <main class="grid w-full max-w-4xl overflow-hidden bg-white shadow-2xl md:grid-cols-2">
        <div class="hidden min-h-130 bg-[linear-gradient(135deg,rgba(143,36,29,.9),rgba(55,16,13,.78)),url('https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=1200&q=80')] bg-cover bg-center p-10 text-white md:flex md:flex-col md:justify-between"><span class="text-xl font-black tracking-[.2em]">RESMAP</span><div><p class="text-sm font-bold uppercase tracking-[.25em] text-[#ffd5c9]">Centro de operaciones</p><h1 class="mt-3 text-4xl font-black leading-tight">Todo el trabajo, en un solo lugar.</h1></div></div>
        <div class="p-8 sm:p-12"><a href="{{ route('home') }}" class="text-sm font-bold text-[#9f2f25]">← Volver al sitio</a><h2 class="mt-12 text-3xl font-black">Acceso interno</h2><p class="mt-2 text-gray-500">Ingresa con tu usuario o correo.</p>
        @if(session('status'))<div class="mt-6 border-l-4 border-green-600 bg-green-50 p-4 text-sm text-green-900" role="status">{{ session('status') }}</div>@endif
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
            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
                <div><label for="login" class="block text-sm font-bold">Usuario o correo</label><input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}" class="mt-2 w-full border border-gray-300 p-3 outline-none focus:border-[#b52f25]"></div>
                <div>
                    <label for="password" class="block text-sm font-bold">Contraseña</label>
                    <div class="relative mt-2">
                        <input id="password" type="password" name="password" required autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="w-full border border-gray-300 p-3 pr-20 outline-none focus:border-[#b52f25]">
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
                <button class="w-full bg-[#b52f25] p-3 font-bold text-white transition hover:bg-[#8f241d]">Ingresar</button>
                <a href="{{ route('password.request') }}" class="block text-center text-sm font-bold text-[#9f2f25]">¿Olvidaste tu contraseña?</a>
            </form>
        </div>
    </main>
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
