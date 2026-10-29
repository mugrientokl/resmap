<?php

use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\NoStoreMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Boost\Middleware\InjectBoost;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:backup-database')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onFailure(function (): void {
                Log::critical('Falló la tarea diaria de backup de la base de datos.');
            });
        $schedule->command('app:liberar-reservas-vencidas')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
        $middleware->appendToGroup('web', [ForceHttps::class, InjectBoost::class, NoStoreMiddleware::class]);
        $middleware->alias(['role' => RoleMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if ($request->expectsJson()
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException) {
                return null;
            }

            $status = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof TokenMismatchException => 419,
                default => 500,
            };

            $error = match ($status) {
                401 => ['title' => 'Necesitas iniciar sesión', 'message' => 'Inicia sesión para continuar con esta operación.'],
                403 => ['title' => 'No tienes permiso para ingresar', 'message' => 'Tu cuenta no tiene acceso a esta sección.'],
                404 => ['title' => 'No encontramos esta página', 'message' => 'Puede que el enlace haya cambiado o que el contenido ya no esté disponible.'],
                405 => ['title' => 'Esta acción no está disponible', 'message' => 'Vuelve a la página anterior e intenta una acción válida.'],
                410 => ['title' => 'Este enlace ya no está disponible', 'message' => 'La cotización pudo haber vencido o ya fue respondida. Si necesitas revisarla, comunícate con RESMAP.'],
                419 => ['title' => 'Tu sesión venció', 'message' => 'Actualiza la página e intenta nuevamente. Si estabas enviando información, comprueba primero si se guardó.'],
                429 => ['title' => 'Demasiados intentos', 'message' => 'Espera un momento antes de volver a intentarlo.'],
                503 => ['title' => 'Estamos realizando mejoras', 'message' => 'El servicio estará disponible nuevamente en breve.'],
                default => ['title' => 'Ocurrió un problema', 'message' => 'No pudimos completar la operación. Inténtalo nuevamente o comunícate con RESMAP.'],
            };

            return response()->view('errors.system', [
                'status' => $status,
                'title' => $error['title'],
                'message' => $error['message'],
            ], $status);
        });
    })->create();
