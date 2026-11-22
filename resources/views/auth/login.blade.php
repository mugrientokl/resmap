<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso interno | RESMAP</title>@vite(['resources/css/app.css', 'resources/js/face-recognition.js'])<link rel="stylesheet" href="{{ asset('css/global-controls.css') }}"><script defer src="{{ asset('js/notifications.js') }}"></script></head>
<body class="flex min-h-screen items-center justify-center bg-[#8f241d] px-6 py-8 text-[#241817]">
    <main class="grid w-full max-w-4xl overflow-hidden bg-white shadow-2xl md:grid-cols-2">
        <div class="hidden min-h-130 bg-[linear-gradient(135deg,rgba(143,36,29,.9),rgba(55,16,13,.78)),url('https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=1200&q=80')] bg-cover bg-center p-10 text-white md:flex md:flex-col md:justify-between"><span class="text-xl font-black tracking-[.2em]">RESMAP</span><div><p class="text-sm font-bold uppercase tracking-[.25em] text-[#ffd5c9]">Centro de operaciones</p><p class="mt-3 text-4xl font-black leading-tight">Todo el trabajo, en un solo lugar.</p></div></div>
        <div class="p-8 sm:p-12"><a href="{{ route('home') }}" class="text-sm font-bold text-[#9f2f25]">← Volver al sitio</a><h1 class="mt-12 text-3xl font-black">Acceso interno</h1><p class="mt-2 text-gray-500">Ingresa con tu usuario y contraseña. También puedes agregar verificación facial como segundo factor.</p>
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
                    <label for="login" class="block text-sm font-bold">Usuario o correo</label>
                    <input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}" class="mt-2 w-full border border-[#dccac6] bg-[#fffcfb] p-3 outline-none transition focus:border-[#b52f25] focus:ring-2 focus:ring-[#f3d4ce]">
                </div>
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
                            <span id="toggle-password-label">Mostrar</span>
                        </button>
                    </div>
                </div>
                <button class="w-full bg-[#b52f25] p-3 font-bold text-white shadow-md transition hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">Ingresar</button>
            </form>
            <form method="POST" action="{{ route('login.face') }}" class="mt-4" data-face-mode="login" data-models-url="{{ asset('models') }}" data-face-login-url="{{ route('login.face') }}" data-credential-form="#password-login-form">@csrf
                <button type="button" data-face-open class="flex w-full items-center justify-center gap-2 border border-[#d6aaa3] bg-[#fff9f7] p-3 font-bold text-[#8f241d] transition hover:bg-[#f7e8e6] focus:outline-none focus:ring-2 focus:ring-[#b52f25]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7.5h3l1.5-2h7L17 7.5h3v11H4z"/><circle cx="12" cy="13" r="3.5" stroke-width="1.8"/></svg>
                    Verificar rostro e ingresar
                </button>
            </form>
            <a href="{{ route('password.request') }}" class="mt-5 block text-center text-sm font-bold text-[#9f2f25] hover:text-[#721d18] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#b52f25]">¿Olvidaste tu contraseña?</a>
        </div>
    </main>
    @include('components.face-camera')
    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('toggle-password');
        const togglePasswordLabel = document.getElementById('toggle-password-label');

        togglePassword?.addEventListener('click', () => {
            const mostrar = passwordInput.type === 'password';
            passwordInput.type = mostrar ? 'text' : 'password';
            togglePassword.setAttribute('aria-pressed', String(mostrar));
            togglePassword.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
            togglePasswordLabel.textContent = mostrar ? 'Ocultar' : 'Mostrar';
            passwordInput.focus();
        });
    </script>
</body>
</html>
