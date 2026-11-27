<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\PlanPerdidaInventario;
use Symfony\Component\HttpKernel\Exception\HttpException;
function verify(bool $v,string $msg):void{if(!$v)throw new RuntimeException($msg);}
function reject(callable $fn,int $status):void{try{$fn();}catch(HttpException $e){verify($e->getStatusCode()===$status,'HTTP incorrecto');return;}throw new RuntimeException('No rechazado');}

function motive(int $code):object{return (object)['codigo'=>$code,'activo'=>1,'grupo_codigo'=>300,'regla'=>'MERMA','naturaleza'=>'SALIDA','politica_cpp'=>'VIGENTE','ajuste_rapido'=>0,'requiere_referencia'=>0,'requiere_aprobacion'=>1,'requiere_respaldo'=>1,'revision_tributaria'=>1,'evaluar_aviso_sii'=>0];}
verify(config('app.env')==='testing','Solo CI');
$p=new PlanPerdidaInventario();
$s=(object)['disponible'=>'5.125','transito'=>'1','comprometido'=>'2','cantidad_fisica'=>'8.125'];
foreach([301,302,303,304] as $code){
 $v=$p->calcular(motive($code),'0.125',$s,'500.1234');
 verify($v['disponible_actual']==='5.00000000'&&$v['fisico_actual']==='8.00000000'&&$v['cpp_actual']==='500.12340000'&&$v['valor_movimiento']==='62.5154250000000000','Salida exacta');
 verify($v['comprometido']==='2.00000000'&&$v['transito']==='1.00000000'&&$v['revision_tributaria']==='PENDIENTE','Controles');
}
reject(fn()=>$p->calcular(motive(301),'5.126',$s,'500'),409);
reject(fn()=>$p->calcular(motive(301),'1',$s,'0'),409);
foreach([305,306,401,410,601] as $code)reject(fn()=>$p->calcular(motive($code),'1',$s,'500'),409);
foreach(['requiere_referencia'=>1,'requiere_respaldo'=>0,'evaluar_aviso_sii'=>1,'revision_tributaria'=>0,'politica_cpp'=>'HISTORICO'] as $k=>$v){$m=motive(301);$m->$k=$v;reject(fn()=>$p->calcular($m,'1',$s,'500'),409);}
reject(fn()=>$p->calcular(motive(301),'0.000000001',$s,'500'),422);
echo "V109F1_MERMAS_OK\n";
