<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\PlanReservaInventario;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $fn,int $status=409):void{try{$fn();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'Estado inesperado');return;}throw new RuntimeException('Faltó rechazo');}
check(config('app.env')==='testing','Solo CI');
$motor=new PlanReservaInventario();
$base=['activo'=>1,'grupo_codigo'=>700,'politica_cpp'=>'CONSERVAR','ajuste_rapido'=>0,'requiere_referencia'=>1,'requiere_aprobacion'=>1,'requiere_respaldo'=>0,'revision_tributaria'=>0,'evaluar_aviso_sii'=>0];
$reserve=(object)($base+['codigo'=>710,'regla'=>'RESERVAR']);$release=(object)($base+['codigo'=>711,'regla'=>'LIBERAR_RESERVA']);
$stock=(object)['disponible'=>'10.00000001','comprometido'=>'2','transito'=>'3','cantidad_fisica'=>'15.00000001'];
$p=$motor->calcular($reserve,'4.12345678',$stock,'120.12345678','0');
check($p['posterior']===['disponible'=>'5.87654323','transito'=>'3.00000000','comprometido'=>'6.12345678','cantidad_fisica'=>'15.00000001'],'Reserva exacta');
check($p['delta_disponible']==='-4.12345678'&&$p['delta_comprometido']==='4.12345678'&&$p['pendiente_actual']==='4.12345678','Deltas');
check($p['cpp_actual']===$p['cpp_anterior']&&$p['cpp_actual']==='120.12345678'&&$p['delta_fisico']==='0.00000000','CPP y físico conservados');
$q=$motor->calcular($release,'1.12345678',(object)$p['posterior'],'125.12345678',$p['pendiente_actual']);
check($q['pendiente_actual']==='3.00000000'&&$q['posterior']['comprometido']==='5.00000000'&&$q['cpp_actual']==='125.12345678','Liberación parcial y CPP vigente');
$r=$motor->calcular($release,'3',(object)$q['posterior'],'125.12345678',$q['pendiente_actual']);
check($r['pendiente_actual']==='0.00000000'&&$r['posterior']===$p['anterior'],'Liberación total restaura saldo');
reject(fn()=>$motor->calcular($reserve,'10.00000002',$stock,'120','0'));
reject(fn()=>$motor->calcular($reserve,'1',$stock,'120','1'));
reject(fn()=>$motor->calcular($release,'1',$stock,'120','0'));
reject(fn()=>$motor->calcular($release,'3',$stock,'120','3'));
reject(fn()=>$motor->calcular($release,'2.00000001',(object)$p['posterior'],'120','2'));
reject(fn()=>$motor->calcular($reserve,'1',(object)array_replace((array)$stock,['cantidad_fisica'=>'9']),'120','0'));
reject(fn()=>$motor->calcular($reserve,'1.000000001',$stock,'120','0'),422);
reject(fn()=>$motor->calcular($reserve,1.1,$stock,'120','0'),422);
reject(fn()=>$motor->calcular($reserve,'0',$stock,'120','0'),422);
foreach(['requiere_referencia'=>0,'requiere_aprobacion'=>0,'politica_cpp'=>'VIGENTE','regla'=>'DESPACHAR','activo'=>0] as $k=>$v)reject(fn()=>$motor->calcular((object)array_replace((array)$reserve,[$k=>$v]),'1',$stock,'120','0'));
check($motor->calcular($reserve,'1',$stock,'0','0')['cpp_actual']==='0.00000000','Reclasificación no inventa CPP');
echo "V109E1_RESERVAS_OK\n";
