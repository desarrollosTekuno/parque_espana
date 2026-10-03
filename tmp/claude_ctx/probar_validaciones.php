<?php
// Prueba de las reglas de carga. Todo corre dentro de una transaccion que se revierte al final: no guarda nada.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Migration\Dinero\DineroMigrationService;
use App\Services\Migration\Socios\SociosMigrationService;
use Illuminate\Support\Facades\DB;

$buena = base_path('database/data/Plantilla_Migracion_Cliente.xlsx');
$mala = __DIR__ . '/Plantilla_Reglas_Prueba.xlsx';
$sinCartas = 'C:\\no-existe';

$mostrar = function (string $titulo, array $r, int $max = 60) {
    echo "\n=== {$titulo}: " . count($r['errors']) . ' errores, ' . count($r['warnings'] ?? []) . " avisos\n";
    foreach (array_slice($r['errors'], 0, $max) as $e) {
        echo "  E {$e['sheet']} f" . ($e['row'] ?? '-') . " {$e['field']}: {$e['message']}\n";
    }
    foreach (array_slice($r['warnings'] ?? [], 0, 15) as $w) {
        echo "  A {$w['sheet']} f" . ($w['row'] ?? '-') . " {$w['field']}: {$w['message']}\n";
    }
};

DB::beginTransaction();
try {
    $socios = app(SociosMigrationService::class);
    $mostrar('SOCIOS plantilla buena', $socios->review($buena, false, $sinCartas));
    $mostrar('SOCIOS plantilla con errores', $socios->review($mala, false, $sinCartas));

    $socios->run($buena, false, false, $sinCartas, 'local');
    echo "\nSocios cargados (dentro de la transaccion)\n";

    $dinero = app(DineroMigrationService::class);
    $mostrar('DINERO plantilla buena', $dinero->review($buena));
    $mostrar('DINERO plantilla con errores', $dinero->review($mala));

    $dinero->run($buena);
    echo "\nDinero cargado (dentro de la transaccion)\n";
    echo json_encode([
        'mensualidades_sin_vencimiento' => DB::table('billing.charges as c')->join('billing.concepts as k', 'k.id', '=', 'c.concept_id')
            ->whereIn('k.code', App\Services\Billing\MembershipChargeService::MONTHLY_FEE_FAMILY_CODES)->whereNull('c.due_date')->count(),
        'cuentas_con_piso' => DB::table('memberships.accounts')->whereNotNull('billing_backfill_floor')->count(),
        'pisos' => DB::table('memberships.accounts')->whereNotNull('billing_backfill_floor')->groupBy('billing_backfill_floor')->selectRaw('billing_backfill_floor, count(*) n')->pluck('n', 'billing_backfill_floor'),
        'membresias_ambos_parques_con_regla' => DB::table('memberships.memberships as m')->leftJoin('memberships.pricing_rules as p', 'p.id', '=', 'm.pricing_rule_id')
            ->where(fn ($q) => $q->whereNotNull('m.interclub_package_rule_id')->orWhere('p.requires_multiple_clubs', true))->count(),
        'historial_bajas' => DB::table('memberships.membership_history')->where('reason', 'Baja voluntaria de cuenta')->count(),
        'pase_mensual_con_fin' => DB::table('memberships.memberships')->whereNotNull('end_date')->where('status', 'active')->count(),
    ], JSON_PRETTY_PRINT), "\n";
} finally {
    DB::rollBack();
    echo "\nTransaccion revertida: no se guardo nada.\n";
}
