<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $identity = Str::lower($credentials['login']);
        $limiterKey = 'login-failures:'.hash('sha256', $identity.'|'.$request->ip());
        $lockKey = 'login-lock:'.hash('sha256', $identity.'|'.$request->ip());

        if (Cache::has($lockKey)) {
            return back()->withErrors(['login' => 'Por seguridad, espera antes de volver a intentarlo.'])->withInput()
                ->setStatusCode(429);
        }

        if (! Auth::validate([$field => $credentials['login'], 'password' => $credentials['password']])) {
            $failures = RateLimiter::hit($limiterKey, 86400);
            if ($failures >= 5) {
                $delay = min(900, 60 * (2 ** min($failures - 5, 4)));
                Cache::put($lockKey, true, $delay);
            }

            Log::warning('auth.login.failed', [
                'ip' => $request->ip(),
                'identity_hash' => hash('sha256', $identity),
            ]);

            return back()->withErrors(['login' => 'Las credenciales no son válidas.'])->withInput();
        }

        RateLimiter::clear($limiterKey);
        Cache::forget($lockKey);

        $user = User::query()->where($field, $credentials['login'])->firstOrFail();
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
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data);

        return back()->with('status', 'Si el correo está registrado, recibirás un enlace para restablecer la contraseña.');
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
        ]);

        $status = Password::reset($data, function ($user, $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => null])->save();
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            Log::notice('auth.password.reset', ['user_id' => $user->id, 'ip' => request()->ip()]);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
