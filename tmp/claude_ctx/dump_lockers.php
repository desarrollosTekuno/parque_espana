<?php
// Solo lectura: resumen del inventario de casilleros a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$out = [
  'resumen' => DB::select("select c.code club, l.category, l.status, count(*) n, min(l.number) min_num, max(l.number) max_num from members.lockers l join clubs.clubs c on c.id=l.club_id where l.deleted_at is null group by c.code, l.category, l.status order by 1,2,3"),
  'concepto' => DB::select("select code, name, default_amount from billing.concepts where code ilike '%LOCKER%'"),
  'importes_club' => DB::select("select c.code club, x.* from billing.concept_club_amounts x join billing.concepts k on k.id=x.concept_id join clubs.clubs c on c.id=x.club_id where k.code ilike '%LOCKER%'"),
];
file_put_contents(__DIR__.'/lockers.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "OK\n";
