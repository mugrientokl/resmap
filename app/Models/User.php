<?php

namespace App\Models;

use App\Notifications\NuevaCotizacionPendiente;
use App\Notifications\ProductoStockCritico;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SolicitudWebRecibida;
use App\Support\ConfiguracionSistema;
use App\Support\TextoPersona;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'name', 'apellido_paterno', 'apellido_materno', 'run', 'email', 'telefono', 'password', 'rol', 'tamano_fuente'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasFactory, Notifiable, SoftDeletes;

    protected $attributes = [
        'activo' => true,
        'rol' => 'Vendedor',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => TextoPersona::normalizar($valor));
    }

    protected function apellidoPaterno(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => TextoPersona::normalizar($valor));
    }

    protected function apellidoMaterno(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => TextoPersona::normalizar($valor));
    }

    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => $valor === null ? null : mb_strtolower(trim($valor), 'UTF-8'));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'permisos' => 'array',
        ];
    }

    public function tienePermiso(string $permiso): bool
    {
        if ($this->rol === 'Administrador') {
            return true;
        }

        return $this->rol === 'Vendedor'
            && array_key_exists($permiso, config('permisos.opciones'))
            && in_array($permiso, $this->permisos ?? config('permisos.vendedor_actual'), true);
    }

    public function notificacionesVisibles(): MorphMany
    {
        $consulta = $this->notifications();
        $tipos = [];
        if ($this->tienePermiso('productos.ver') && ConfiguracionSistema::notificacionActiva('stock_critico')) {
            $tipos[] = ProductoStockCritico::class;
        }
        if ($this->tienePermiso('cotizaciones.ver')) {
            if (ConfiguracionSistema::notificacionActiva('solicitudes')) {
                $tipos[] = SolicitudWebRecibida::class;
            }
            if (ConfiguracionSistema::notificacionActiva('cotizaciones')) {
                $tipos[] = NuevaCotizacionPendiente::class;
            }
        }

        return $consulta->whereIn('type', $tipos);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'user_id', 'id');
    }

    public function movimientosInventario()
    {
        return $this->hasMany(InventarioMovimiento::class, 'user_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
