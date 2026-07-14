<?php

namespace App\Services\Billing;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FolioService {

    public function generate(User $cajero, Carbon $paidAt = null) {
        if ($paidAt === null) {
            $paidAt = now();
        }

        $consecutivo = $this->obtenerSiguienteConsecutivo($cajero, $paidAt);

        $consecutivoTexto = str_pad($consecutivo, 3, '0', STR_PAD_LEFT);

        $anio = $paidAt->format('y');
        $mes = $paidAt->format('m');
        $dia = $paidAt->format('d');

        $fechaCompleta = $anio . $mes . $dia;

        $folioCompleto = $cajero->code . '-' . $fechaCompleta . '-' . $consecutivoTexto;

        $folioCorto = $cajero->code . '-' . $dia . $consecutivoTexto;

        return [
            'folio_completo' => $folioCompleto,
            'folio_corto' => $folioCorto,
        ];
    }

    public function resetFolio(User $cajero) {
        $cajero->last_folio_number = 0;
        $cajero->last_folio_date = null;
        $cajero->save();
    }

    private function obtenerSiguienteConsecutivo(User $cajero, Carbon $paidAt) {
        $consecutivo = DB::transaction(function () use ($cajero, $paidAt) {
            $usuarioBloqueado = User::query()
                ->whereKey($cajero->id)
                ->lockForUpdate()
                ->first();

            $esElMismoDia = false;

            if ($usuarioBloqueado->last_folio_date !== null) {
                $ultimaFecha = Carbon::parse($usuarioBloqueado->last_folio_date);

                if ($ultimaFecha->isSameDay($paidAt)) {
                    $esElMismoDia = true;
                }
            }

            if ($esElMismoDia) {
                $siguienteNumero = $usuarioBloqueado->last_folio_number + 1;
            } else {
                $siguienteNumero = 1;
            }

            $usuarioBloqueado->last_folio_number = $siguienteNumero;
            $usuarioBloqueado->last_folio_date = $paidAt->toDateString();
            $usuarioBloqueado->save();

            return $siguienteNumero;
        });

        return $consecutivo;
    }
}
