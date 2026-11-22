<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('notificaciones:depurar')]
#[Description('Command description')]
class DepurarNotificaciones extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cantidad = DB::table('notifications')
            ->where('created_at', '<', now()->subDays(180))
            ->delete();

        $this->info("Notificaciones eliminadas por antigüedad: {$cantidad}.");

        return self::SUCCESS;
    }
}
