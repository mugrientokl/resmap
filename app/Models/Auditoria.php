<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $primaryKey = 'id_auditoria';

    protected $fillable = [
        'user_id', 'modelo', 'modelo_id', 'accion', 'datos_anteriores',
        'datos_nuevos', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
