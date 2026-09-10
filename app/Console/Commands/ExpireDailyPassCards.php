<?php

namespace App\Console\Commands;

use App\Services\Access\GuestPassProvisioningService;
use Illuminate\Console\Command;
use App\Models\Devices\DailyPassCard;

class ExpireDailyPassCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:expire-daily-pass-cards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expira las tarjetas de pases diarios cuya vigencia ya venció, liberando espacio en los usuarios invitados';

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
        $this->info('Buscando tarjetas de pases diarios vencidas...');
        $this->info('');

        $expiredCards = DailyPassCard::where('status', 'active')
            ->where('valid_until', '<', now())
            ->get();

        $this->info("Tarjetas vencidas encontradas: {$expiredCards->count()}");
        $this->info('');

        $expirados = 0;
        $errores = [];

        foreach ($expiredCards as $card) {
            try {
                $this->guestPassProvisioningService->expireCard($card);
                $expirados++;
            } catch (\Throwable $e) {
                $errores[] = [
                    'card_id' => $card->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->info('');
        $this->info('═══════════════════════════════════════════');
        $this->info('  REPORTE DE EXPIRACIÓN DE PASES DIARIOS');
        $this->info('═══════════════════════════════════════════');
        $this->info('');
        $this->info("  Tarjetas expiradas: {$expirados}");
        $this->info("  Errores: " . count($errores));
        $this->info('');

        foreach ($errores as $error) {
            $this->warn("  ↳ Tarjeta {$error['card_id']}: {$error['message']}");
        }

        $this->info('');

        return empty($errores) ? self::SUCCESS : self::FAILURE;
        
    }
}
