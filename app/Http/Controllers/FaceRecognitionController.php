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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
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

    public function login(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric', 'between:-1,1'],
        ], [
            'login.required' => 'Ingresa tu usuario o correo antes de verificar tu rostro.',
            'password.required' => 'Ingresa tu contraseña para confirmar el acceso.',
            'face_descriptor.required' => 'No pudimos leer el rostro. Inténtalo nuevamente.',
            'face_descriptor.size' => 'No pudimos leer el rostro. Inténtalo nuevamente.',
        ]);

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $identity = Str::lower($validated['login']);
        $limiterKey = 'login-failures:'.hash('sha256', $identity.'|'.$request->ip());
        $lockKey = 'login-lock:'.hash('sha256', $identity.'|'.$request->ip());
        $user = User::query()->where($field, $validated['login'])->first();
        $validPassword = Auth::validate([
            $field => $validated['login'],
            'password' => $validated['password'],
        ]);
        $faceMatches = $user !== null
            && is_array($user->face_descriptor)
            && $this->descriptorsMatch($validated['face_descriptor'], $user->face_descriptor);
        $authorized = $validPassword
            && $user !== null
            && $user->activo
            && in_array($user->rol, ['Administrador', 'Vendedor'], true)
            && $faceMatches;

        if (Cache::has($lockKey) && ! $authorized) {
            $request->session()->flashInput(['login' => $validated['login']]);
            $errors = (new ViewErrorBag)->put(
                'default',
                new MessageBag([
                    'login' => 'Hubo varios intentos fallidos. Espera unos minutos antes de volver a intentarlo.',
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
                'login' => 'Rostro no reconocido o credenciales incorrectas. Verifica los datos e inténtalo nuevamente.',
            ])->withInput(['login' => $validated['login']]);
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

        return redirect()->intended(route('dashboard'))->with('status', 'Acceso autorizado.');
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
