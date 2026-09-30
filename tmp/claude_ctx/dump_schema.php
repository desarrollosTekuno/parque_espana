<?php
// Solo lectura: exporta estructura de la BD (columnas, FKs, conteos) a JSON.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$excl = "('pg_catalog','information_schema','pg_toast')";
$cols = DB::select("select table_schema s, table_name t, column_name c, data_type dt, udt_name udt, is_nullable n, column_default d, character_maximum_length len, ordinal_position o from information_schema.columns where table_schema not in $excl and table_schema not like 'pg_%' order by 1,2,o");
$fks = DB::select("select tc.table_schema s, tc.table_name t, kcu.column_name c, ccu.table_schema fs, ccu.table_name ft, ccu.column_name fc from information_schema.table_constraints tc join information_schema.key_column_usage kcu on tc.constraint_name=kcu.constraint_name and tc.table_schema=kcu.table_schema join information_schema.constraint_column_usage ccu on ccu.constraint_name=tc.constraint_name where tc.constraint_type='FOREIGN KEY'");
$uniq = DB::select("select schemaname s, tablename t, indexname i, indexdef d from pg_indexes where schemaname not in $excl and schemaname not like 'pg_%'");
$checks = DB::select("select n.nspname s, c.relname t, pg_get_constraintdef(k.oid) d from pg_constraint k join pg_class c on c.oid=k.conrelid join pg_namespace n on n.oid=c.relnamespace where k.contype='c' and n.nspname not in $excl");
$tables = DB::select("select table_schema s, table_name t, table_type ty from information_schema.tables where table_schema not in $excl and table_schema not like 'pg_%'");
$counts = [];
foreach ($tables as $tb) { if ($tb->ty !== 'BASE TABLE') continue; try { $counts[$tb->s.'.'.$tb->t] = DB::selectOne('select count(*) c from "'.$tb->s.'"."'.$tb->t.'"')->c; } catch (\Throwable $e) { $counts[$tb->s.'.'.$tb->t] = 'ERR'; } }
file_put_contents(__DIR__.'/schema.json', json_encode(compact('cols','fks','uniq','checks','tables','counts'), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "OK ".count($tables)." tablas\n";
