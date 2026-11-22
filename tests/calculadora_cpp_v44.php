<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\CalculadoraCostoPromedioRecepcion;
use Illuminate\Support\Facades\DB;

if(DB::getDatabaseName()!=='rutahyd2_es_local'){
    throw new RuntimeException('Se prohíbe esta prueba fuera de la base temporal CI');
}
$calculadora=new CalculadoraCostoPromedioRecepcion();
$casos=[
    ['10','1000.0000',[['cantidad'=>'5','costo'=>'1600']],'1200.0000'],
    ['10','1000',[['cantidad'=>'5','costo'=>'1300'],['cantidad'=>'5','costo'=>'1700']],'1250.0000'],
    ['0','0',[['cantidad'=>'2.500000','costo'=>'450.1250']],'450.1250'],
    ['1.500000','100',[['cantidad'=>'0.500000','costo'=>'300']],'150.0000'],
    ['0','0',[['cantidad'=>'0.000001','costo'=>'0.0001']],'0.0001'],
];
foreach($casos as $i=>[$q0,$c0,$lines,$expected]){
    $actual=$calculadora->calcular($q0,$c0,$lines);
    if($actual!==$expected){
        throw new RuntimeException("CPP V44 caso ".($i+1).": esperado $expected, obtenido $actual");
    }
}
$invalido=[
    ['3','0',[['cantidad'=>'1','costo'=>'100']]],
    ['0','0',[['cantidad'=>'0','costo'=>'100']]],
    ['0','0',[['cantidad'=>'1.0000001','costo'=>'100']]],
    ['-1','100',[['cantidad'=>'1','costo'=>'100']]],
    ['0','0',[]],
];
foreach($invalido as $i=>[$q0,$c0,$lines]){
    try{$calculadora->calcular($q0,$c0,$lines);}
    catch(InvalidArgumentException){continue;}
    throw new RuntimeException('Validación de costo V44 omitida en caso '.($i+1));
}
echo "V44_CPP_OK 5 escenarios exactos y 5 rechazos; ninguna escritura de datos.\n";
