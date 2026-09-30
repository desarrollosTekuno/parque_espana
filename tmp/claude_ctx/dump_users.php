<?php
// Solo lectura: usuarios del panel (sin contrasenas ni tokens) y roles a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$out = [
 'users' => DB::select("select u.id,u.name,u.email,u.code,
     (select string_agg(r.name||' ['||coalesce(c.name,'')||']', ', ') from public.model_has_roles m join public.roles r on r.id=m.role_id left join public.contexts c on c.id=r.context_id where m.model_id=u.id and m.model_type like '%User') roles,
     (select string_agg(cl.name, ', ') from public.user_clubs uc join clubs.clubs cl on cl.id=uc.club_id where uc.user_id=u.id and uc.deleted_at is null) clubs,
     (select count(*) from members.members mm where mm.user_id=u.id) es_socio
   from public.users u order by u.id"),
 'roles' => DB::select("select r.name, r.description, c.name ctx from public.roles r left join public.contexts c on c.id=r.context_id order by r.id"),
];
file_put_contents(__DIR__.'/users.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "OK\n";
