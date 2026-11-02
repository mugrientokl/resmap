<?php

namespace App\Providers;

use App\Listeners\RegistrarEventoAutenticacion;
use App\Models\User;
use App\Observers\AuditoriaObserver;
use App\Services\Facturacion\FacturacionSimulada;
use App\Services\Facturacion\ProveedorFacturacion;
use App\Services\RegistroActividad;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegistroActividad::class);
    }

    public function boot(): void
    {
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

        Gate::define('ver-actividad', fn (User $usuario): bool => $usuario->rol === 'Administrador');

        Event::listen(Login::class, [RegistrarEventoAutenticacion::class, 'login']);
        Event::listen(Logout::class, [RegistrarEventoAutenticacion::class, 'logout']);
        Event::listen(PasswordReset::class, [RegistrarEventoAutenticacion::class, 'passwordReset']);
    }
}
