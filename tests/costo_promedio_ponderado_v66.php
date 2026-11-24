<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use App\Services\CostoPromedioPonderado;

$servicio = new CostoPromedioPonderado();
$casos = [
    ['primera compra',['0','0','50','65000',null],'1300.00000000'],
    ['reinicio sin stock',['0','2500','50','65000','0'],'1300.00000000'],
    ['recuperacion historica',['100','0','50','65000','100000'],'1100.00000000'],
    ['promedio normal',['100','1000','50','65000','100000'],'1100.00000000'],
    ['cero documentado',['100','0','50','65000','0'],'433.33333333'],
    ['consolidacion',['100','1000','50','64000','100000'],'1093.33333333'],
    ['precision',['2','0','1','1','0'],'0.33333333'],
];
foreach ($casos as [$nombre,$args,$esperado]) {
    $r = $servicio->calcular(...$args);
    if ($r['cpp_posterior'] !== $esperado) {
        throw new RuntimeException($nombre.': '.$r['cpp_posterior'].' != '.$esperado);
    }
}
$invalidos = [
    ['100','0','50','65000',null],
    ['100','1000','50','65000','99999'],
    ['-1','0','50','65000',null],
    ['0','0','0','0',null],
    ['0','0','50','65000','1'],
];
foreach ($invalidos as $args) {
    try {
        $servicio->calcular(...$args);
    } catch (DomainException $e) {
        continue;
    }
    throw new RuntimeException('Se acepto una valorizacion invalida.');
}
echo "CPP: 12 casos aprobados\n";
