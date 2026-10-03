<?php
// Solo lectura: conteos del estado actual de la base local.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
echo json_encode([
  'members' => DB::table('members.members')->count(),
  'migrados' => DB::table('members.members')->whereNotNull('migration_origin_id')->count(),
  'cuentas' => DB::table('memberships.accounts')->count(),
  'cuentas_con_interno' => DB::table('memberships.accounts')->whereNotNull('internal_account_number')->count(),
  'cargos' => DB::table('billing.charges')->count(),
  'cargos_mig' => DB::table('billing.charges')->whereRaw("metadata->>'migration_source'='cliente'")->count(),
  'pagos' => DB::table('billing.payments')->count(),
  'casilleros_asig' => DB::table('members.locker_assignments')->count(),
  'conceptos' => DB::table('billing.concepts')->orderBy('id')->pluck('code', 'name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
