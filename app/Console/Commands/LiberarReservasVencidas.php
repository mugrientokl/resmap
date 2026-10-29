<?php

namespace App\Console\Commands;

use App\Services\InventarioReservaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:liberar-reservas-vencidas')]
#[Description('Command description')]
class LiberarReservasVencidas extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InventarioReservaService $reservas): int
    {
        $liberadas = $reservas->liberarVencidas();
        $this->info("Reservas vencidas liberadas: {$liberadas}.");

        return self::SUCCESS;
    }
}
