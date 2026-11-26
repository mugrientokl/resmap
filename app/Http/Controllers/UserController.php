<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\CorreoConDominio;
use App\Rules\RutChileno;
use App\Services\RegistroActividad;
use App\Support\Rut;
use App\Support\TelefonoChileno;
use App\Support\TextoPersona;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function store(Request $request): Response|JsonResponse
    {
        $correo = $request->input('correo', $request->input('email'));
        $telefono = $request->input('telefono', $request->input('telefono_fijo'));
        $run = $request->input('run', $request->input('rut'));

        $request->merge([
            'correo' => $correo,
            'telefono' => TelefonoChileno::normalizar(is_string($telefono) ? $telefono : null),
            'run' => is_string($run) ? Rut::normalizar($run) : null,
            'nombre' => TextoPersona::normalizar($request->input('nombre')),
            'apellido_paterno' => TextoPersona::normalizar($request->input('apellido_paterno')),
            'apellido_materno' => TextoPersona::normalizar($request->input('apellido_materno')),
        ]);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['required', 'string', 'max:255'],
            'run' => ['required', 'string', 'max:20', new RutChileno, 'unique:users,run'],
            'correo' => ['required', 'email', new CorreoConDominio, 'max:255', 'unique:users,email'],
            'telefono' => ['required', 'string', 'regex:/^\+56[2-9][0-9]{8}$/'],
            'rol' => 'required|in:Administrador,Vendedor',
            'permisos_configurados' => ['sometimes', 'boolean'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['string', 'distinct', Rule::in(array_keys(config('permisos.opciones')))],
        ]);

        $nombre = trim($validated['nombre']);
        $apellidoPaterno = trim($validated['apellido_paterno']);
        $apellidoMaterno = trim($validated['apellido_materno']);
        $contrasenaInicial = Str::random(32);
        $user = DB::transaction(function () use ($validated, $nombre, $apellidoPaterno, $apellidoMaterno, $contrasenaInicial): User {
            $username = $this->generarUsername($nombre, $apellidoMaterno);
            $user = User::create([
                'username' => $username,
                'name' => trim($nombre.' '.$apellidoPaterno.' '.$apellidoMaterno),
                'apellido_paterno' => $apellidoPaterno,
                'apellido_materno' => $apellidoMaterno,
                'run' => $validated['run'],
                'email' => $validated['correo'],
                'telefono' => $validated['telefono'],
                'rol' => $validated['rol'],
                'password' => $contrasenaInicial,
            ]);

            if ($user->rol === 'Vendedor' && (isset($validated['permisos']) || ($validated['permisos_configurados'] ?? false))) {
                $user->forceFill(['permisos' => $this->normalizarPermisos($validated['permisos'] ?? [])])->save();
            }

            return $user;
        });

        if (! $request->expectsJson()) {
            return response()->view('usuarios.contrasena-inicial', compact('user', 'contrasenaInicial'));
        }

        return response()->json([
            'message' => 'Cuenta creada. La contraseña inicial se muestra una sola vez.',
            'initial_password' => $contrasenaInicial,
            'user' => $user,
        ], 201);
    }

    private function generarUsername(string $nombre, string $apellidoMaterno): string
    {
        $nombreSlug = Str::of(Str::ascii(mb_strtolower($nombre, 'UTF-8')))
            ->lower()
            ->trim()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();
        $apellidoSlug = Str::of(Str::ascii(mb_strtolower($apellidoMaterno, 'UTF-8')))
            ->lower()
            ->trim()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();
        $base = trim($nombreSlug.'.'.$apellidoSlug, '.');

        $ocupados = User::withTrashed()
            ->where('username', 'like', $base.'%')
            ->lockForUpdate()
            ->pluck('username');
        $mayorSufijo = 1;
        $baseExiste = false;

        foreach ($ocupados as $ocupado) {
            if (! is_string($ocupado)) {
                continue;
            }
            if ($ocupado === $base) {
                $baseExiste = true;

                continue;
            }
            if (preg_match('/^'.preg_quote($base, '/').'(?:([0-9]+))$/', $ocupado, $coincidencia)) {
                $mayorSufijo = max($mayorSufijo, (int) ($coincidencia[1] ?: 1));
            }
        }

        if (! $baseExiste && $mayorSufijo === 1) {
            return $base;
        }

        return $base.(++$mayorSufijo);
    }

    public function actualizarPermisos(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_unless($user->rol === 'Vendedor', 403);

        $validated = $request->validate([
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['string', 'distinct', Rule::in(array_keys(config('permisos.opciones')))],
        ]);
        $user->forceFill(['permisos' => $this->normalizarPermisos($validated['permisos'] ?? [])])->save();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Permisos actualizados.', 'user' => $user]);
        }

        return redirect()->route('usuarios.index')->with('success', 'Permisos actualizados.');
    }

    /**
     * @param  array<int, string>  $permisos
     * @return array<int, string>
     */
    private function normalizarPermisos(array $permisos): array
    {
        foreach ($permisos as $permiso) {
            [$modulo, $accion] = explode('.', $permiso, 2);
            $permisoVer = $modulo.'.ver';
            if ($accion !== 'ver' && array_key_exists($permisoVer, config('permisos.opciones'))) {
                $permisos[] = $permisoVer;
            }
        }

        return array_values(array_unique($permisos));
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

    public function eliminarDefinitivamente(Request $request, int $user): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($user): void {
            $usuario = User::withTrashed()->whereKey($user)->lockForUpdate()->firstOrFail();
            abort_unless($usuario->rol === 'Vendedor', 403);

            $referencias = [
                'ventas' => ['user_id', 'credito_autorizado_por'],
                'inventario_movimientos' => ['user_id'],
                'auditorias' => ['user_id'],
                'cotizaciones' => ['creada_por'],
                'cotizacion_versiones' => ['revisada_por', 'respuesta_registrada_por'],
                'ordenes_compra' => ['autorizada_por'],
                'eventos_facturacion' => ['user_id'],
                'eventos_reserva' => ['user_id'],
            ];

            foreach ($referencias as $tabla => $columnas) {
                foreach ($columnas as $columna) {
                    if (DB::table($tabla)->where($columna, $usuario->id)->exists()) {
                        throw ValidationException::withMessages([
                            'usuario' => 'No se puede eliminar la cuenta porque tiene registros asociados. Puedes archivarla para conservar su historial.',
                        ]);
                    }
                }
            }

            DB::table(config('session.table'))->where('user_id', $usuario->id)->delete();
            DB::table(config('auth.passwords.users.table'))->where('email', $usuario->email)->delete();
            $usuario->notifications()->delete();
            $usuario->forceDelete();
        });

        $message = 'La cuenta fue eliminada definitivamente.';

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
