<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RegistroActividad;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UserController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $archivados = $request->boolean('archivados');
        $consultaUsuarios = $archivados
            ? User::onlyTrashed()
            : User::query();
        $usuarios = $consultaUsuarios
            ->orderByDesc('activo')
            ->orderBy('name')
            ->get();

        return $request->expectsJson()
            ? response()->json($usuarios)
            : view('usuarios.index', compact('usuarios', 'archivados'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'rol' => 'required|in:Administrador,Vendedor',
        ]);

        $user = User::create([
            ...$validated,
            'password' => Str::random(64),
        ]);
        $enlaceEnviado = $this->enviarEnlaceRestablecimiento($user);
        $message = $enlaceEnviado
            ? 'Cuenta creada. Enviamos al usuario un enlace para que defina su contraseña.'
            : 'Cuenta creada, pero no se pudo enviar el enlace. El usuario puede solicitarlo desde “Olvidé mi contraseña”.';

        if (! $request->expectsJson()) {
            return redirect()->route('usuarios.index')->with(
                $enlaceEnviado ? 'success' : 'warning',
                $message,
            );
        }

        return response()->json([
            'message' => $message,
            'password_setup_link_sent' => $enlaceEnviado,
            'user' => $user,
        ], 201);
    }

    public function actualizarEstado(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_unless($user->rol === 'Vendedor', 403);

        $validated = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);
        $activo = (bool) $validated['activo'];

        if ($user->activo === $activo) {
            return $this->respuestaEstadoUsuario($request, $user, $activo);
        }

        if (! $activo) {
            DB::transaction(function () use ($user): void {
                $user->forceFill([
                    'activo' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table(config('session.table'))
                    ->where('user_id', $user->id)
                    ->delete();

                DB::table(config('auth.passwords.users.table'))
                    ->where('email', $user->email)
                    ->delete();
            });
        } else {
            $user->forceFill(['activo' => true])->save();
        }

        return $this->respuestaEstadoUsuario($request, $user, $activo);
    }

    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_unless($user->rol === 'Vendedor', 403);

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'activo' => false,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table(config('session.table'))
                ->where('user_id', $user->id)
                ->delete();

            DB::table(config('auth.passwords.users.table'))
                ->where('email', $user->email)
                ->delete();

            $user->delete();
        });

        $message = 'La cuenta fue archivada. Se conservaron las ventas y el historial de auditoría.';

        if (! $request->expectsJson()) {
            return redirect()->route('usuarios.index')->with('success', $message);
        }

        return response()->json(['message' => $message]);
    }

    public function restaurar(Request $request, int $user): RedirectResponse|JsonResponse
    {
        $usuarioArchivado = User::withTrashed()->findOrFail($user);
        abort_unless($usuarioArchivado->trashed() && $usuarioArchivado->rol === 'Vendedor', 403);

        $usuarioArchivado->forceFill(['activo' => true])->restore();

        app(RegistroActividad::class)->evento(
            categoria: 'seguridad',
            accion: 'cuenta_vendedor_restaurada',
            descripcion: 'Restauró la cuenta archivada del vendedor '.$usuarioArchivado->name,
            modelo: User::class,
            modeloId: $usuarioArchivado->id,
            nuevos: ['activo' => true, 'archivada' => false],
        );

        $message = 'La cuenta fue restaurada y quedó habilitada.';

        if (! $request->expectsJson()) {
            return redirect()->route('usuarios.index')->with('success', $message);
        }

        return response()->json(['message' => $message]);
    }

    public function enviarEnlaceContrasena(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_unless($user->rol === 'Vendedor' && $user->activo, 403);

        $enlaceEnviado = $this->enviarEnlaceRestablecimiento($user);
        $message = $enlaceEnviado
            ? 'Enviamos un enlace para definir o restablecer la contraseña.'
            : 'No se pudo enviar el enlace. Verifica el correo del usuario y vuelve a intentarlo.';

        if (! $request->expectsJson()) {
            return redirect()->route('usuarios.index')->with(
                $enlaceEnviado ? 'success' : 'warning',
                $message,
            );
        }

        return response()->json([
            'message' => $message,
            'password_setup_link_sent' => $enlaceEnviado,
        ], $enlaceEnviado ? 200 : 503);
    }

    private function enviarEnlaceRestablecimiento(User $user): bool
    {
        try {
            return Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
        } catch (TransportExceptionInterface $exception) {
            Log::warning('No se pudo enviar el enlace para definir la contraseña del usuario.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function respuestaEstadoUsuario(
        Request $request,
        User $user,
        bool $activo,
    ): RedirectResponse|JsonResponse {
        $message = $activo
            ? 'La cuenta del vendedor está habilitada.'
            : 'La cuenta del vendedor está inhabilitada.';

        if (! $request->expectsJson()) {
            return redirect()->route('usuarios.index')->with('success', $message);
        }

        return response()->json([
            'message' => $message,
            'user' => $user,
        ]);
    }
}
