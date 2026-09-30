<?php
// Solo lectura: datos de clubes (sin credenciales) a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$out = [
 'clubs' => DB::select('select * from clubs.clubs order by id'),
 'addresses' => DB::select('select a.*, co.name country, s.name state, ci.name city from clubs.club_addresses a left join catalogs.countries co on co.id=a.country_id left join catalogs.states s on s.id=a.state_id left join catalogs.cities ci on ci.id=a.city_id'),
 'rules' => DB::select('select * from clubs.rules'),
];
file_put_contents(__DIR__.'/clubs.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "OK\n";
