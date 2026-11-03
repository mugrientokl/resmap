<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RegistroActividad;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse|Response
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'login.required' => 'Ingresa tu usuario o correo.',
            'login.string' => 'El usuario o correo debe ser texto válido.',
            'login.max' => 'El usuario o correo no puede superar los 255 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'La contraseña debe ser texto válido.',
            'password.max' => 'La contraseña no puede superar los 255 caracteres.',
        ]);
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $identity = Str::lower($credentials['login']);
        $limiterKey = 'login-failures:'.hash('sha256', $identity.'|'.$request->ip());
        $lockKey = 'login-lock:'.hash('sha256', $identity.'|'.$request->ip());

        $validPassword = Auth::validate([
            $field => $credentials['login'],
            'password' => $credentials['password'],
        ]);
        $user = User::query()->where($field, $credentials['login'])->first();

        if (Cache::has($lockKey)
            && (! $validPassword || $user === null || ! $user->activo)
        ) {
            $request->session()->flashInput($request->except('password'));
            $errors = (new ViewErrorBag)->put(
                'default',
                new MessageBag([
                    'login' => 'Hubo varios intentos fallidos. Espera unos minutos antes de volver a intentarlo.',
                ])
            );

            return response()->view('auth.login', compact('errors'), 429);
        }

        if (! $validPassword
            || $user === null
            || ! $user->activo
        ) {
            $failures = RateLimiter::hit($limiterKey, 900);
            if ($failures >= 5) {
                $delay = min(900, 60 * (2 ** min($failures - 5, 4)));
                Cache::put($lockKey, true, $delay);
            }

            Log::warning('auth.login.failed', [
                'ip' => $request->ip(),
                'identity_hash' => hash('sha256', $identity),
            ]);

            app(RegistroActividad::class)->evento(
                categoria: 'seguridad',
                accion: 'inicio_sesion_fallido',
                descripcion: 'Intento de inicio de sesión fallido',
                modelo: 'Autenticación',
                resultado: 'fallo',
                nuevos: ['identidad_hash' => substr(hash('sha256', $identity), 0, 16), 'intentos' => $failures],
            );

            return back()->withErrors([
                'login' => 'El usuario o correo y la contraseña no coinciden, o la cuenta no está disponible.',
            ])->withInput();
        }

        RateLimiter::clear($limiterKey);
        Cache::forget($lockKey);

        Log::info('auth.login.password_accepted', ['user_id' => $user->id, 'ip' => $request->ip()]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId !== null) {
            Log::info('auth.logout', ['user_id' => $userId, 'ip' => $request->ip()]);
        }

        return redirect()->route('home')->header('Clear-Site-Data', '"cache"');
    }

    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Ingresa el correo asociado a tu cuenta.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);
        $usuarioActivo = User::query()
            ->where('email', $data['email'])
            ->where('activo', true)
            ->exists();

        if ($usuarioActivo) {
            Password::sendResetLink($data);
        }

        return back()->with(
            'status',
            'Si el correo está asociado a una cuenta activa, recibirás instrucciones. Si pediste un enlace recientemente, revisa tu correo antes de solicitar otro.'
        );
    }

    public function showResetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'max:255', PasswordRule::min(12)],
        ], [
            'token.required' => 'El enlace de restablecimiento no es válido. Solicita uno nuevo.',
            'email.required' => 'Ingresa el correo asociado al enlace.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa una nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.max' => 'La contraseña no puede superar los 255 caracteres.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
        ]);

        $status = Password::reset($data, function ($user, $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => null])->save();
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            Log::notice('auth.password.reset', ['user_id' => $user->id, 'ip' => request()->ip()]);
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Tu contraseña fue restablecida. Ya puedes iniciar sesión.');
        }

        $mensaje = match ($status) {
            Password::INVALID_TOKEN => 'El enlace venció o ya fue utilizado. Solicita uno nuevo para continuar.',
            Password::INVALID_USER => 'No encontramos una cuenta activa asociada a ese correo.',
            Password::RESET_THROTTLED => 'Espera un momento antes de solicitar otro restablecimiento.',
            default => 'No pudimos restablecer la contraseña. Solicita un nuevo enlace e inténtalo otra vez.',
        };

        return back()->withErrors(['email' => $mensaje]);
    }
}
