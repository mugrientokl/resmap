<?php

namespace App\Observers;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditoriaObserver
{
    public function created(Model $modelo): void
    {
        $this->registrar($modelo, 'creado', null, $modelo->getAttributes());
    }

    public function updated(Model $modelo): void
    {
        $this->registrar($modelo, 'editado', $modelo->getOriginal(), $modelo->getChanges());
    }

    public function deleted(Model $modelo): void
    {
        $this->registrar($modelo, 'eliminado', $modelo->getOriginal(), null);
    }

    private function registrar(Model $modelo, string $accion, ?array $anteriores, ?array $nuevos): void
    {
        Auditoria::create([
            'user_id' => Auth::id(),
            'modelo' => $modelo::class,
            'modelo_id' => $modelo->getKey(),
            'accion' => $accion,
            'datos_anteriores' => $anteriores,
            'datos_nuevos' => $nuevos,
            'ip' => request()->ip(),
        ]);
    }
}
