<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\PlanAjusteGeneral;
use Symfony\Component\HttpKernel\Exception\HttpException;
function verify(bool $v,string $msg):void{if(!$v)throw new RuntimeException($msg);}
function reject(callable $fn,int $status):void{try{$fn();}catch(HttpException $e){verify($e->getStatusCode()===$status,'HTTP incorrecto');return;}throw new RuntimeException('No rechazado');}
function motive(int $code):object{
 return (object)['codigo'=>$code,'activo'=>1,'grupo_codigo'=>100,'regla'=>match($code){101=>'AJUSTE_POSITIVO',102=>'AJUSTE_NEGATIVO',107=>'RECUPERAR_EXISTENCIA',default=>'SALDO_INICIAL'},'naturaleza'=>$code===102?'SALIDA':'ENTRADA','politica_cpp'=>'VIGENTE','ajuste_rapido'=>0,'requiere_referencia'=>0,'requiere_aprobacion'=>1,'requiere_respaldo'=>1,'revision_tributaria'=>1,'evaluar_aviso_sii'=>0];
}
verify(config('app.env')==='testing','Solo CI');
$p=new PlanAjusteGeneral();
$s=(object)['disponible'=>'5.125','transito'=>'1','comprometido'=>'2','cantidad_fisica'=>'8.125'];
foreach([101,107] as $code){
 $v=$p->calcular(motive($code),'0.125',$s,'500.1234');
 verify($v['disponible_actual']==='5.25000000'&&$v['fisico_actual']==='8.25000000'&&$v['cpp_actual']==='500.12340000'&&$v['valor_movimiento']==='62.5154250000000000','Entrada exacta');
 verify($v['requiere_respaldo']&&$v['requiere_aprobacion']&&$v['revision_tributaria']==='PENDIENTE','Controles');
}
$v=$p->calcular(motive(102),'5.125',$s,'500');
verify($v['disponible_actual']==='0.00000000'&&$v['fisico_actual']==='3.00000000'&&$v['delta_disponible']==='-5.12500000'&&$v['transito']==='1.00000000'&&$v['comprometido']==='2.00000000','Salida no toca reservas ni tránsito');
reject(fn()=>$p->calcular(motive(102),'5.126',$s,'500'),409);
reject(fn()=>$p->calcular(motive(101),'1',$s,'0'),409);
foreach(['0','-1','1e2','1x5','0.000000001'] as $q)reject(fn()=>$p->calcular(motive(101),$q,$s,'500'),422);
reject(fn()=>$p->calcular(motive(106),'1',$s,'500'),409);
$m=motive(101);$m->requiere_respaldo=0;reject(fn()=>$p->calcular($m,'1',$s,'500'),409);
$broken=clone $s;$broken->cantidad_fisica='99';reject(fn()=>$p->calcular(motive(101),'1',$broken,'500'),409);
echo "V109B_PLAN_OK\n";
