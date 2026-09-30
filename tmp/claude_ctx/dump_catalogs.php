<?php
// Solo lectura: exporta catalogos pequenos (sin columnas sensibles) a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$q = [
 'clubs' => 'select id,code,name,applies_iva,is_active from clubs.clubs',
 'types' => 'select t.id,c.code club,t.code,t.name,t.allows_multiple_members,t.requires_origin_family,t.validity_months,t.show_in_listing from memberships.types t join clubs.clubs c on c.id=t.club_id order by 1',
 'concepts' => 'select id,code,name,default_amount,is_recurring,allows_partial_payments,requires_account,applies_iva,splits_between_parks,is_active from billing.concepts order by 1',
 'payment_methods' => 'select id,code,name,provider,affects_cash_cut,show_in_billing,is_active from billing.payment_methods',
 'relationships' => 'select * from catalogs.relationships',
 'roles' => 'select r.id,r.name,r.description,c.name ctx from public.roles r left join public.contexts c on c.id=r.context_id',
 'contexts' => 'select * from public.contexts',
 'cancellation_reasons' => 'select code,name from catalogs.cancellation_reasons',
 'separation_reasons' => 'select code,name,requires_document from memberships.separation_reasons',
 'res_status' => 'select id,name from reservations.status',
 'files' => 'select code,name,module,is_required from files.files',
 'res_vars' => 'select name,value,club_id from reservations.system_variables',
 'gl_vars' => 'select code,name,value,club_id from guest_lists.variables',
 'app_vars' => 'select name,value,club_id from mobile_app.variables',
 'doc_types' => 'select code,name,is_club_specific from catalogs.document_types',
 'accounts' => 'select a.membership_number,a.internal_account_number,a.account_type,a.status,a.account_group_id,a.billing_backfill_floor from memberships.accounts a',
 'ad_statuses' => 'select name from advertising.business_ad_statuses',
 'feedback_cats' => 'select code,name from feedback.categories',
];
$out=[]; foreach($q as $k=>$s){ $out[$k]=DB::select($s); }
file_put_contents(__DIR__.'/catalogs.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "OK\n";
