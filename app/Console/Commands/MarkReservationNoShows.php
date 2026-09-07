<?php

namespace App\Console\Commands;

use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Illuminate\Console\Command;

class MarkReservationNoShows extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:mark-no-shows';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marca como inasistencia las reservaciones activas cuyo horario ya terminó sin registrar asistencia (check-in por QR)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Reservation::where('reservation_status_id', ReservationStatus::ACTIVA)
            ->where('end_datetime', '<', now())
            ->update(['reservation_status_id' => ReservationStatus::INASISTENCIA]);

        $this->info("Reservaciones marcadas como inasistencia: {$count}");

        return 0;
    }
}
