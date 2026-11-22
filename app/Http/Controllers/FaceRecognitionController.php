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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class FaceRecognitionController extends Controller
{
    private const FACE_MATCH_THRESHOLD = 0.6;

    public function showProfile(Request $request): View
    {
        return view('profile.show', [
            'faceRegistered' => $request->user()->face_descriptor !== null,
            'faceConsentAt' => $request->user()->face_consent_at,
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username,'.auth()->id()],
            'current_password' => ['required', 'current_password'],
        ], [
            'name.required' => 'Ingresa el nombre que deseas mostrar.',
            'username.required' => 'Ingresa el nombre de usuario.',
            'username.regex' => 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ]);

        $request->user()->forceFill([
            'name' => trim($validated['name']),
            'username' => $validated['username'],
        ])->save();

        return redirect()->route('perfil.show')->with('success', 'Tu nombre de usuario fue actualizado.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'max:255', PasswordRule::min(12)],
        ], [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingresa una nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.max' => 'La contraseña no puede superar los 255 caracteres.',
        ]);

        $user = $request->user();
        DB::transaction(function () use ($request, $user, $validated): void {
            $user->forceFill(['password' => $validated['password'], 'remember_token' => null])->save();
            DB::table(config('session.table'))->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())->delete();
            DB::table(config('auth.passwords.users.table'))->where('email', $user->email)->delete();
        });

        return redirect()->route('perfil.show')->with('success', 'Tu contraseña fue actualizada.');
    }

    public function login(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric', 'between:-1,1'],
        ], [
            'login.required' => 'Ingresa tu usuario o correo antes de verificar tu rostro.',
            'password.required' => 'Ingresa tu contraseña antes de verificar tu rostro.',
            'face_descriptor.required' => 'No pudimos leer el rostro. Inténtalo nuevamente.',
            'face_descriptor.size' => 'No pudimos leer el rostro. Inténtalo nuevamente.',
        ]);

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $identity = mb_strtolower($validated['login'], 'UTF-8');
        $limiterKey = 'face-login-failures:'.hash('sha256', $identity.'|'.$request->ip());
        $lockKey = 'face-login-lock:'.hash('sha256', $identity.'|'.$request->ip());
        $user = User::query()
            ->where($field, $validated['login'])
            ->where('activo', true)
            ->whereIn('rol', ['Administrador', 'Vendedor'])
            ->whereNotNull('face_descriptor')
            ->first();
        $validPassword = Auth::validate([
            $field => $validated['login'],
            'password' => $validated['password'],
        ]);
        $authorized = $validPassword
            && $user !== null
            && is_array($user->face_descriptor)
            && $this->descriptorsMatch($validated['face_descriptor'], $user->face_descriptor);

        if (Cache::has($lockKey) && ! $authorized) {
            $errors = (new ViewErrorBag)->put(
                'default',
                new MessageBag([
                    'face' => 'Hubo varios intentos fallidos. Espera unos minutos antes de volver a intentarlo.',
                ])
            );

            return response()->view('auth.login', compact('errors'), 429);
        }

        if (! $authorized) {
            $failures = RateLimiter::hit($limiterKey, 900);

            if ($failures >= 5) {
                $delay = min(900, 60 * (2 ** min($failures - 5, 4)));
                Cache::put($lockKey, true, $delay);
            }

            Log::warning('auth.face_login.failed', [
                'ip' => $request->ip(),
                'identity_hash' => hash('sha256', $identity),
            ]);

            app(RegistroActividad::class)->evento(
                categoria: 'seguridad',
                accion: 'inicio_sesion_facial_fallido',
                descripcion: 'Intento de inicio de sesión facial fallido',
                modelo: 'Autenticación',
                resultado: 'fallo',
                nuevos: ['identidad_hash' => substr(hash('sha256', $identity), 0, 16), 'intentos' => $failures],
            );

            return back()->withErrors([
                'face' => 'Las credenciales o el rostro no coinciden con una cuenta disponible.',
            ]);
        }

        RateLimiter::clear($limiterKey);
        Cache::forget($lockKey);
        Auth::login($user);
        $request->session()->regenerate();

        Log::info('auth.face_login.accepted', ['user_id' => $user->id, 'ip' => $request->ip()]);
        app(RegistroActividad::class)->evento(
            categoria: 'seguridad',
            accion: 'inicio_sesion_facial_exitoso',
            descripcion: 'Inicio de sesión facial completado con contraseña',
            modelo: 'Autenticación',
            modeloId: $user->id,
        );

        return redirect()->intended(route($user->tienePermiso('dashboard.ver') ? 'dashboard' : 'perfil.show'))->with('status', 'Acceso autorizado.');
    }

    public function enroll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric', 'between:-1,1'],
        ], [
            'password.required' => 'Ingresa tu contraseña para confirmar el registro.',
            'consent.accepted' => 'Debes aceptar el uso de tu descriptor facial para continuar.',
            'face_descriptor.required' => 'No pudimos leer el rostro. Inténtalo nuevamente.',
            'face_descriptor.size' => 'No pudimos leer el rostro. Inténtalo nuevamente.',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña no es correcta.',
            ]);
        }

        $user->forceFill([
            'face_descriptor' => $validated['face_descriptor'],
            'face_consent_at' => now(),
        ])->save();

        app(RegistroActividad::class)->evento(
            categoria: 'seguridad',
            accion: 'rostro_registrado',
            descripcion: 'Registró o actualizó su descriptor facial',
            modelo: User::class,
            modeloId: $user->id,
            nuevos: ['rostro_registrado' => true],
        );

        return back()->with('success', 'Tu rostro se registró de forma cifrada. Para el acceso facial también se solicitará tu contraseña.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ], [
            'password.required' => 'Ingresa tu contraseña para eliminar el registro facial.',
        ]);
        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña no es correcta.',
            ]);
        }

        $user->forceFill([
            'face_descriptor' => null,
            'face_consent_at' => null,
        ])->save();

        app(RegistroActividad::class)->evento(
            categoria: 'seguridad',
            accion: 'rostro_eliminado',
            descripcion: 'Eliminó su registro facial',
            modelo: User::class,
            modeloId: $user->id,
            anteriores: ['rostro_registrado' => true],
            nuevos: ['rostro_registrado' => false],
        );

        return back()->with('success', 'El registro facial fue eliminado. Puedes seguir usando tu contraseña para ingresar.');
    }

    private function descriptorsMatch(array $captured, array $registered): bool
    {
        if (count($captured) !== 128 || count($registered) !== 128) {
            return false;
        }

        $squaredDistance = 0.0;

        foreach ($captured as $index => $value) {
            if (! is_numeric($value) || ! is_numeric($registered[$index] ?? null)) {
                return false;
            }

            $difference = (float) $value - (float) $registered[$index];
            $squaredDistance += $difference * $difference;
        }

        return sqrt($squaredDistance) <= self::FACE_MATCH_THRESHOLD;
    }
}
