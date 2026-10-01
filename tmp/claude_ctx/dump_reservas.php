<?php
// Solo lectura: catalogos de amenidades, reservaciones, invitados y clases a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$out = [
  'amenities' => DB::select("select a.id, c.code club, a.name, a.reservation_type, a.is_active from amenities.amenities a join clubs.clubs c on c.id=a.club_id where a.deleted_at is null order by 1"),
  'resources' => DB::select("select r.id, r.amenity_id, r.name, r.capacity, r.is_active, r.slot_duration_minutes from amenities.resources r where r.deleted_at is null order by 2,1"),
  'schedules' => DB::select("select amenity_id, day_of_week, open_time, close_time from amenities.schedules where deleted_at is null order by 1,2"),
  'status' => DB::select("select id, name from reservations.status where deleted_at is null order by 1"),
  'res_vars' => DB::select("select v.name, v.description, v.value, c.code club from reservations.system_variables v left join clubs.clubs c on c.id=v.club_id where v.deleted_at is null order by 1"),
  'gl_vars' => DB::select("select v.code, v.name, v.value, c.code club from guest_lists.variables v left join clubs.clubs c on c.id=v.club_id where v.deleted_at is null order by 1"),
  'specialties' => DB::select("select * from classes.specialties"),
  'gl_concepts' => DB::select("select code, name, default_amount from billing.concepts where code ilike '%GUEST%' or code ilike '%CLASS%' or name ilike '%clase%' or name ilike '%invitad%'"),
];
file_put_contents(__DIR__.'/reservas.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "OK\n";
