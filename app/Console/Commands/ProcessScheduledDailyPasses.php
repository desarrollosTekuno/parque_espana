<?php

namespace App\Console\Commands;

use App\Models\Devices\DailyPassCard;
use App\Services\Access\GuestPassProvisioningService;
use Illuminate\Console\Command;

class ProcessScheduledDailyPasses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:process-scheduled-daily-passes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activa en el dispositivo las tarjetas de pase diario programadas para hoy';

    public function __construct(
        private GuestPassProvisioningService $guestPassProvisioningService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('');
        $this->info('Buscando tarjetas programadas para hoy...');
        $this->info('');

        // valid_until = fin del día de la visita (23:59:59), así que
        // "programada para hoy" es cualquier scheduled cuyo valid_until
        // caiga dentro del día de hoy.
        $scheduledCards = DailyPassCard::where('status', 'scheduled')
            ->whereDate('valid_until', now()->addDay()->toDateString())
            ->get();

        $this->info("Tarjetas a activar: {$scheduledCards->count()}");
        $this->info('');

        $activadas = 0;
        $errores = [];

        foreach ($scheduledCards as $card) {
            try {
                $this->guestPassProvisioningService->activateScheduledCard($card);
                $activadas++;
            } catch (\Throwable $e) {
                $errores[] = [
                    'card_id' => $card->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->info('');
        $this->info('═══════════════════════════════════════════');
        $this->info('  REPORTE DE ACTIVACIÓN DE PASES PROGRAMADOS');
        $this->info('═══════════════════════════════════════════');
        $this->info('');
        $this->info("  Tarjetas activadas: {$activadas}");
        $this->info("  Errores: " . count($errores));
        $this->info('');

        foreach ($errores as $error) {
            $this->warn("  ↳ Tarjeta {$error['card_id']}: {$error['message']}");
        }

        $this->info('');

        return empty($errores) ? self::SUCCESS : self::FAILURE;
    }
}
