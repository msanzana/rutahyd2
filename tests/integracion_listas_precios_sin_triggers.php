<?php
declare(strict_types=1);

/** Únicamente CI efímera: PROHIBIDO ejecutar sobre datos reales. */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\GestionListasPrecios;
use Illuminate\Support\Facades\DB;

function verificarLista(bool $correcto, string $mensaje): void
{
    if (!$correcto) throw new RuntimeException($mensaje);
}
function debeFallar(callable $operacion, string $nombre): void
{
    try {
        $operacion();
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        verificarLista(in_array($e->getStatusCode(),[409,422],true),
            $nombre.' debe rechazar con 409/422');
        return;
    }
    throw new RuntimeException('Debía fallar: '.$nombre);
}
try {
    verificarLista(DB::getDatabaseName()==='rutahyd2_es_local',
        'PROHIBIDO: esta prueba solo funciona en una base efímera de CI');
    $servicio=$app->make(GestionListasPrecios::class);
    $baseId=$servicio->listaBaseId();
    verificarLista(DB::table('listas_precios')->where('es_base',1)->count()===1,
        'Solo una LISTA BASE');
    $triggers=DB::table('information_schema.TRIGGERS')
        ->where('TRIGGER_SCHEMA',DB::getDatabaseName())
        ->whereIn('EVENT_OBJECT_TABLE',[
            'listas_precios','listas_precios_detalle','clientes_listas_precios'
        ])->count();
    verificarLista($triggers===0,'El modelo no utiliza triggers');

    $clienteId=(int)DB::table('clientes')->insertGetId([
        'codigo'=>'CLI-PRECIOS-CI','nombre'=>'Cliente Tarifas CI','activo'=>1,
    ]);
    $servicio->asignarListaBaseCliente($clienteId);
    $servicio->asignarListaBaseCliente($clienteId);
    verificarLista(DB::table('clientes_listas_precios')
        ->where('cliente_id',$clienteId)->count()===1,
        'Asignación base debe ser idempotente y única');

    $lineaId=DB::table('lineas')->orderBy('id')->value('id');
    verificarLista($lineaId!==null,'Falta una línea del catálogo temporal');
    $productoId=(int)DB::table('productos')->insertGetId([
        'sku'=>'SKU-LISTAS-CI','nombre'=>'Producto con listas CI',
        'linea_id'=>$lineaId,'costo_neto'=>0,'activo'=>1,
    ]);
    DB::table('presentaciones_productos')->insert([
        'producto_id'=>$productoId,'nombre'=>'Unidad',
        'unidades_por_envase'=>1,'precio_neto'=>1350,'tasa_iva'=>19,'activo'=>1,
    ]);
    $servicio->asegurarPrecioBaseProducto($productoId);
    $servicio->asegurarPrecioBaseProducto($productoId);
    $base=DB::table('listas_precios_detalle')
        ->where('producto_id',$productoId)->where('lista_precio_id',$baseId)->first();
    verificarLista($base!==null && (float)$base->precio_venta===1350.0
        && $base->margen_porcentaje===null,'Precio histórico conservado; margen indeterminado');
    verificarLista(DB::table('listas_precios_detalle')
        ->where('producto_id',$productoId)->count()===1,
        'Alta de producto solo una vez, sin duplicados');

    debeFallar(fn()=>$servicio->crearLista('BASE','Otra base',true),
        'Impedir segunda lista base');
    $especialId=$servicio->crearLista('ESPECIAL_CI','Tarifa Especial CI');
    debeFallar(fn()=>$servicio->actualizarLista($baseId,['activo'=>false]),
        'Lista Base no se puede desactivar');
    debeFallar(fn()=>$servicio->actualizarLista($especialId,['es_base'=>true]),
        'Una lista especial no puede convertirse en segunda base');
    $editada=$servicio->actualizarLista($especialId,['nombre'=>'Tarifa Especial Actualizada CI']);
    verificarLista($editada['nombre']==='Tarifa Especial Actualizada CI',
        'El backend permite editar la descripción comercial de una lista especial');
    DB::table('lineas')->where('id',$lineaId)
        ->update(['minimo_margen_precio_venta'=>20]);

    debeFallar(fn()=>$servicio->guardarPrecioLista(
        $productoId,$especialId,1000,null,5
    ),'Bloquear margen menor al mínimo de su línea');
    $p=$servicio->guardarPrecioLista($productoId,$especialId,1000,null,25);
    verificarLista(abs($p['precio_venta']-1250)<0.0001,
        'Precio se calcula como costo + 25%');
    $p=$servicio->guardarPrecioLista($productoId,$especialId,1000,1500,null);
    verificarLista(abs($p['margen_porcentaje']-50)<0.0001,
        'Margen se calcula desde precio y costo');
    debeFallar(fn()=>$servicio->guardarPrecioLista(
        $productoId,$especialId,1000,1500,25
    ),'Evitar precio y margen contradictorios');
    debeFallar(fn()=>$servicio->guardarPrecioLista(
        $productoId,$especialId,0,1000,null
    ),'Evitar división por cero');

    $servicio->asignarListaCliente($clienteId,$especialId);
    verificarLista(DB::table('clientes_listas_precios')
        ->where('cliente_id',$clienteId)->count()===1,
        'Cliente no puede tener dos listas distintas');
    $servicio->asignarListaBaseCliente($clienteId);
    verificarLista((int)DB::table('clientes_listas_precios')
        ->where('cliente_id',$clienteId)->value('lista_precio_id')===$especialId,
        'Asignar base nunca sobrescribe lista especial');
    $precio=$servicio->precioEfectivoCliente($clienteId,$productoId);
    verificarLista((int)$precio->lista_precio_id===$especialId
        && (float)$precio->precio_venta===1500.0,
        'Venta resuelve la lista especial, no precio base');
    $servicio->asignarListaCliente($clienteId,$baseId);
    $precio=$servicio->precioEfectivoCliente($clienteId,$productoId);
    verificarLista((int)$precio->lista_precio_id===$baseId
        && (float)$precio->precio_venta===1350.0,
        'Al volver a la base se usa el precio general');

    DB::table('productos')->where('id',$productoId)
        ->update(['sku'=>'SKU-LISTAS-CI-2']);
    $servicio->sincronizarSkuProducto($productoId,'SKU-LISTAS-CI-2');
    verificarLista(DB::table('listas_precios_detalle')
        ->where('producto_id',$productoId)
        ->where('sku','SKU-LISTAS-CI-2')->count()===2,
        'SKU sincronizado en todas las listas mediante función Laravel');

    echo "OK: DDL24 sin triggers, Lista Base, asignaciones únicas, precios, márgenes, SKU y venta especial.\n";
} catch (\Throwable $e) {
    fwrite(STDERR,"ERROR LISTAS PRECIOS CI: ".$e->getMessage()."\n");
    exit(1);
}
