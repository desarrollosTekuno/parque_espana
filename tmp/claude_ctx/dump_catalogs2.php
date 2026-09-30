<?php
// Solo lectura: exporta catalogos completos (sin columnas sensibles) a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$q = [
 'clubs' => 'select id,code,name,is_active from clubs.clubs order by id',
 'types' => 'select t.*, c.name club_name from memberships.types t join clubs.clubs c on c.id=t.club_id order by t.id',
 'payment_methods' => 'select * from billing.payment_methods order by id',
 'concepts' => 'select * from billing.concepts order by id',
 'relationships' => 'select * from catalogs.relationships order by id',
 'marital' => 'select * from catalogs.marital_statuses order by id',
 'doc_types' => 'select * from catalogs.document_types order by id',
 'rel_docs' => 'select r.name rel, d.name doc from catalogs.relationships_document_types x join catalogs.relationships r on r.id=x.relationship_id join catalogs.document_types d on d.id=x.document_type_id order by x.id',
 'type_docs' => 'select c.name club, t.name tipo, t.code, d.name doc, x.is_required, x.allow_multiple, x.number_files from memberships.type_required_documents x join memberships.types t on t.id=x.membership_type_id join clubs.clubs c on c.id=t.club_id join catalogs.document_types d on d.id=x.document_type_id order by x.id',
 'cancel' => 'select * from catalogs.cancellation_reasons order by id',
 'separation' => 'select s.*, r.name rel, d.name doc from memberships.separation_reasons s left join catalogs.relationships r on r.id=s.relationship_id left join catalogs.document_types d on d.id=s.document_type_id',
 'discount' => 'select * from billing.annual_discount_rules order by id',
 'pricing' => 'select p.*, t.name tipo, t.code tcode, c.name club, ft.name from_tipo, fc.name from_club from memberships.pricing_rules p join memberships.types t on t.id=p.membership_type_id join clubs.clubs c on c.id=t.club_id left join memberships.types ft on ft.id=p.from_membership_type_id left join clubs.clubs fc on fc.id=ft.club_id order by p.id',
 'pricing_fees' => 'select pricing_rule_id, year, monthly_fee, inscription_fee from memberships.pricing_rule_fee_history order by 1,2',
 'specialties' => 'select * from classes.specialties',
 'res_status' => 'select * from reservations.status order by id',
 'countries' => 'select id,name,iso2,nationality,demonym from catalogs.countries order by id',
 'states' => 'select s.id, c.name country, s.name from catalogs.states s join catalogs.countries c on c.id=s.country_id order by s.id',
 'nationalities' => 'select * from catalogs.nationalities order by id',
 'cities' => 'select c.name country, s.name state, ci.name from catalogs.cities ci join catalogs.countries c on c.id=ci.country_id left join catalogs.states s on s.id=ci.state_id',
];
$out=[]; foreach($q as $k=>$s){ $out[$k]=DB::select($s); }
file_put_contents(__DIR__.'/catalogs2.json', json_encode($out, JSON_UNESCAPED_UNICODE));
echo "OK\n";
