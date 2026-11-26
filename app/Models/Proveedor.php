<?php

namespace App\Models;

use App\Support\TextoPersona;
use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property string $contacto
 */
class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'contacto', 'telefono', 'correo', 'pagina_web'];

    protected function nombre(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => TextoPersona::normalizar($valor));
    }

    protected function contacto(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => TextoPersona::normalizar($valor));
    }
}
