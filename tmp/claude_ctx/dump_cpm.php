<?php
// Solo lectura: metodos de pago por club (sin llaves de Conekta) a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$out = DB::select('select c.name club, pm.name metodo, x.is_active, x.display_order, x.internal_key from billing.club_payment_methods x join clubs.clubs c on c.id=x.club_id join billing.payment_methods pm on pm.id=x.payment_method_id order by x.club_id, x.display_order, x.id');
file_put_contents(__DIR__.'/cpm.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "OK\n";
