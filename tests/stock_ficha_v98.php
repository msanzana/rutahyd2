<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;
function check(bool $v,string $msg):void{if(!$v)throw new RuntimeException($msg);}
check(config('app.env')==='testing'&&DB::getDatabaseName()===':memory:','Solo CI');
DB::statement('CREATE TABLE bodegas(id INTEGER PRIMARY KEY,nombre TEXT,activo INTEGER)');
DB::statement('CREATE TABLE stock(id INTEGER PRIMARY KEY,producto_id INTEGER,bodega_id INTEGER,disponible TEXT,transito TEXT,comprometido TEXT,cantidad_fisica TEXT,UNIQUE(producto_id,bodega_id))');
DB::table('bodegas')->insert([['id'=>1,'nombre'=>'Central','activo'=>1],['id'=>2,'nombre'=>'Sucursal','activo'=>1],['id'=>3,'nombre'=>'Sin stock','activo'=>1],['id'=>4,'nombre'=>'Histórica','activo'=>0],['id'=>5,'nombre'=>'Inactiva vacía','activo'=>0]]);
foreach([[1,7,1,'12.125','2','1','15.125'],[2,7,2,'3','4','0','7'],[3,7,4,'1','0','0','1'],[4,8,1,'999','0','0','999']] as [$id,$pid,$bid,$a,$t,$m,$f])DB::table('stock')->insert(['id'=>$id,'producto_id'=>$pid,'bodega_id'=>$bid,'disponible'=>$a,'transito'=>$t,'comprometido'=>$m,'cantidad_fisica'=>$f]);
$c=$app->make(\App\Http\Controllers\ProductoComercialControlador::class);
$method=new ReflectionMethod($c,'existenciasPorBodega');
$d=$method->invoke($c,7);
$rows=array_column($d['bodegas'],null,'bodega_id');
check(count($rows)===4&&isset($rows[3],$rows[4])&&!isset($rows[5]),'Bodegas activas sin saldo e históricas');
check($rows[3]['cantidad_fisica']==='0'&&$rows[4]['activo']===false,'Cero real e inactiva identificada');
foreach(['disponible'=>'16.125','transito'=>'6','comprometido'=>'1','cantidad_fisica'=>'23.125'] as $k=>$v)check(BigDecimal::of($d['totales'][$k])->isEqualTo($v),'Total '.$k);
$empty=$method->invoke($c,99);check(count($empty['bodegas'])===3&&BigDecimal::of($empty['totales']['cantidad_fisica'])->isZero(),'Producto sin saldo');
check(DB::table('stock')->count()===4,'Consulta sin escrituras');
echo "V98_STOCK_OK\n";
