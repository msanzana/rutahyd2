<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\PlanConteoInventario;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
function check(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function reject(callable $f,int $status):void{try{$f();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'Código inesperado');return;}throw new RuntimeException('Faltó rechazo');}
check(config('app.env')==='testing','Solo CI');
$p=new PlanConteoInventario();
$s=(object)['disponible'=>'5.12345678','transito'=>'2','comprometido'=>'3','cantidad_fisica'=>'10.12345678'];
$x=$p->calcular($s,'8.12345678','500');
check($x['resultado']==='CUADRADO'&&$x['diferencia']==='0.00000000'&&!$x['requiere_ajuste']&&$x['posterior_propuesto']===null,'Conteo exacto excluye tránsito');
$x=$p->calcular($s,'9.12345679','500');
check($x['resultado']==='SOBRANTE'&&$x['motivo_sugerido']===101&&$x['diferencia']==='1.00000001'&&$x['posterior_propuesto']['disponible']==='6.12345679'&&$x['posterior_propuesto']['cantidad_fisica']==='11.12345679'&&$x['valor_diferencia']==='500.0000050000000000','Sobrante exacto');
check($x['posterior_propuesto']['transito']==='2.00000000'&&$x['posterior_propuesto']['comprometido']==='3.00000000'&&$x['posterior_propuesto']['cpp']==='500.00000000'&&$x['requiere_aprobacion']&&$x['requiere_respaldo'],'Protecciones');
$p->verificarVigencia($x['snapshot'],$s,'500.00000000');
reject(fn()=>$p->verificarVigencia($x['snapshot'],$s,'501'),409);
$changed=clone $s;$changed->disponible='5.12345679';$changed->cantidad_fisica='10.12345679';
reject(fn()=>$p->verificarVigencia($x['snapshot'],$changed,'500'),409);
$x=$p->calcular($s,'3','500');check($x['motivo_sugerido']===102&&$x['posterior_propuesto']['disponible']==='0.00000000','Faltante hasta disponible cero');
$x=$p->calcular($s,'2.99999999','500');check($x['requiere_ajuste']&&!$x['ajuste_calculable']&&$x['posterior_propuesto']===null&&in_array('COMPROMETIDO_INSUFICIENTE',$x['bloqueos'],true),'No consumir compromiso');
$x=$p->calcular($s,'0','0');check(!$x['ajuste_calculable']&&count($x['bloqueos'])===2&&$x['valor_diferencia']===null,'Cero contado y costo cero');
$zero=(object)['disponible'=>'0','transito'=>'0','comprometido'=>'0','cantidad_fisica'=>'0'];
$x=$p->calcular($zero,'0','0');check($x['resultado']==='CUADRADO'&&$x['bloqueos']===[],'Cero cuadrado');
$x=$p->calcular($zero,'1','0');check($x['bloqueos']===['CPP_NO_VALORIZADO'],'Sobrante sin costo');
$x=$p->calcular($s,'99999999999999999999.99999999','500');check(in_array('SALDO_FUERA_DE_RANGO',$x['bloqueos'],true),'Desborde');
foreach(['-1','0.000000001','1e3','100000000000000000000','',1.2,null] as $bad)reject(fn()=>$p->calcular($s,$bad,'500'),422);
$bad=clone $s;$bad->cantidad_fisica='1';reject(fn()=>$p->calcular($bad,'2','500'),409);
check($s->disponible==='5.12345678'&&$s->cantidad_fisica==='10.12345678','Cálculo no muta inventario');
echo "V109D1_CONTEO_OK\n";
