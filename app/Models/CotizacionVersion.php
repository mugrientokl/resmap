<?php

namespace App\Models;

use Database\Factories\CotizacionVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CotizacionVersion extends Model
{
    /** @use HasFactory<CotizacionVersionFactory> */
    use HasFactory;

    protected $table = 'cotizacion_versiones';

    protected $primaryKey = 'id_version';

    protected $fillable = [
        'cotizacion_id',
        'datos_cliente',
        'revisada_por',
        'respuesta_registrada_por',
        'numero_version',
        'estado',
        'neto',
        'iva',
        'costo_despacho',
        'total',
        'metodo_entrega',
        'calle',
        'numero',
        'comuna',
        'ciudad',
        'region',
        'referencias',
        'vigente_hasta',
        'token_cliente_hash',
        'token_expira_at',
        'enviada_at',
        'email_estado',
        'whatsapp_estado',
        'aceptada_at',
        'rechazada_at',
        'cambios_solicitados_at',
        'telefono_aceptacion',
        'ip_aceptacion',
    ];

    protected function casts(): array
    {
        return [
            'vigente_hasta' => 'datetime',
            'datos_cliente' => 'array',
            'token_expira_at' => 'datetime',
            'enviada_at' => 'datetime',
            'aceptada_at' => 'datetime',
            'rechazada_at' => 'datetime',
            'cambios_solicitados_at' => 'datetime',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id', 'id_cotizacion');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotDatosCliente(): array
    {
        if (is_array($this->datos_cliente) && $this->datos_cliente !== []) {
            return $this->datos_cliente;
        }

        return [];
    }

    /**
     * @return array{email_estado: string, whatsapp_estado: string, pendiente: bool}
     */
    public function estadosEnvio(bool $esUltimaVersion): array
    {
        $envioVigente = $esUltimaVersion
            && $this->estado === 'enviada'
            && $this->vigente_hasta?->isFuture() === true;
        $estados = [
            'email_estado' => $this->email_estado ?: 'no_enviado',
            'whatsapp_estado' => $this->whatsapp_estado ?: 'no_enviado',
        ];
        foreach ($estados as $canal => $estado) {
            if (! $envioVigente && $estado === 'en_cola') {
                $estados[$canal] = 'cancelado';
            }
        }

        return [...$estados, 'pendiente' => in_array('en_cola', $estados, true)];
    }

    public function cancelarEnviosPendientes(): void
    {
        foreach (['email_estado', 'whatsapp_estado'] as $canal) {
            $this->newQuery()->whereKey($this->getKey())
                ->where($canal, 'en_cola')
                ->update([$canal => 'cancelado']);
        }
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class, 'version_id', 'id_version');
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'version_id', 'id_version');
    }

    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class, 'cotizacion_version_id', 'id_version');
    }
}
