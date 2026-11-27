<?php

namespace App\Providers;

use App\Listeners\RegistrarEventoAutenticacion;
use App\Models\Cliente;
use App\Models\User;
use App\Observers\AuditoriaObserver;
use App\Observers\ClienteContactoObserver;
use App\Services\Facturacion\FacturacionSimulada;
use App\Services\Facturacion\ProveedorFacturacion;
use App\Services\RegistroActividad;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegistroActividad::class);
    }

    public function boot(): void
    {
        View::composer('*', function ($view): void {
            $fondoActivo = DB::table('configuraciones')
                ->where('clave', 'fondo_sistema')
                ->value('valor');

            $urls = [
                'fondo-1' => asset('images/fondo-1.webp'),
                'fondo-2' => asset('images/fondo-2.webp'),
                'fondo-3' => asset('images/fondo-3.webp'),
                'fondo-4' => asset('images/fondo-4.webp'),
            ];

            $url = $urls[(string) $fondoActivo] ?? $urls['fondo-1'];
            if (is_string($fondoActivo) && str_starts_with($fondoActivo, 'personalizado:')) {
                $id = substr($fondoActivo, strlen('personalizado:'));
                $ruta = DB::table('configuraciones')
                    ->where('clave', 'fondo_personalizado:'.$id)
                    ->value('valor');
                if (is_string($ruta) && Storage::disk('public')->exists($ruta)) {
                    $url = Storage::disk('public')->url($ruta);
                }
            }

            $view->with('resmapBackgroundUrl', $url);
        });

        $urlApp = (string) config('app.url');
        if (! $this->app->runningInConsole()
            && str_starts_with($urlApp, 'https://')
            && request()->getHost() === parse_url($urlApp, PHP_URL_HOST)) {
            URL::forceScheme('https');
        }

        $this->app->bind(ProveedorFacturacion::class, FacturacionSimulada::class);

        foreach (array_keys(config('actividad.modelos')) as $modelo) {
            $modelo::observe(AuditoriaObserver::class);
        }

        Cliente::observe(ClienteContactoObserver::class);

        Gate::define('ver-actividad', fn (User $usuario): bool => $usuario->rol === 'Administrador');

        Event::listen(Login::class, [RegistrarEventoAutenticacion::class, 'login']);
        Event::listen(Logout::class, [RegistrarEventoAutenticacion::class, 'logout']);
        Event::listen(PasswordReset::class, [RegistrarEventoAutenticacion::class, 'passwordReset']);
    }
}
