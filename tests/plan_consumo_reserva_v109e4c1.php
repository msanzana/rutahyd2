<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\PlanConsumoReserva;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f,int $status=409):void{try{$f();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'Estado inesperado');return;}throw new RuntimeException('Faltó rechazo');}
check(config('app.env')==='testing','Solo CI');
$m=new PlanConsumoReserva();
$reason=(object)['codigo'=>712,'grupo_codigo'=>700,'activo'=>1,'regla'=>'DESPACHAR','politica_cpp'=>'VIGENTE','ajuste_rapido'=>0,'requiere_referencia'=>1,'requiere_aprobacion'=>1,'requiere_respaldo'=>0,'revision_tributaria'=>1,'indicador_traslado_sii'=>1];
$stock=(object)['disponible'=>'5','transito'=>'2','comprometido'=>'8.12345678','cantidad_fisica'=>'15.12345678'];
$calc=fn($q='2.12345678',$cost='500.12345678',$r='7.12345678',$l='1',$d='1',$commercial='6',$s=null,$mot=null)=>$m->calcular($mot??$reason,$q,$s??$stock,$cost,$r,$l,$d,$commercial);
$x=$calc();
check($x['posterior']===['disponible'=>'5.00000000','transito'=>'2.00000000','comprometido'=>'6.00000000','cantidad_fisica'=>'13.00000000'],'Salida exacta');
check($x['pendiente_anterior']==='5.12345678'&&$x['pendiente_actual']==='3.00000000'&&$x['despachada_actual']==='3.12345678','Consumo propio');
check($x['cpp_actual']===$x['cpp_anterior']&&$x['valor_salida']===(string)\Brick\Math\BigDecimal::of('2.12345678')->multipliedBy('500.12345678')->toScale(16),'Valor histórico de salida');
check($x['revision_tributaria']==='PENDIENTE'&&$x['indicador_traslado_sii']===1,'Revisión no equivale a DTE');
$y=$calc('3','501','7.12345678','1','3.12345678','3',(object)$x['posterior']);
check($y['pendiente_actual']==='0.00000000'&&$y['posterior']['comprometido']==='3.00000000','Consumo total conserva otras reservas');
reject(fn()=>$calc('5.12345679'));
reject(fn()=>$calc('3','500','7.12345678','1','1','2.99'));
reject(fn()=>$calc('1','0'));
reject(fn()=>$calc('1','500','1','1','1'));
reject(fn()=>$calc('1','500','20','0','0'));
reject(fn()=>$calc('0'),422);
reject(fn()=>$calc(1.5),422);
reject(fn()=>$calc('0.000000001'),422);
reject(fn()=>$calc('1','500','7','1','1','6',(object)array_replace((array)$stock,['cantidad_fisica'=>'12'])));
foreach(['codigo'=>711,'politica_cpp'=>'CONSERVAR','requiere_aprobacion'=>0,'requiere_referencia'=>0,'revision_tributaria'=>0,'indicador_traslado_sii'=>5] as $k=>$v)reject(fn()=>$calc('1','500','7','1','1','6',null,(object)array_replace((array)$reason,[$k=>$v])));
echo "V109E4C1_CONSUMO_OK\n";
