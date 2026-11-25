<?php
declare(strict_types=1);
require __DIR__.'/../app/Services/PdfRecepcionCompra.php';
$service=new \App\Services\PdfRecepcionCompra;
$method=new ReflectionMethod($service,'recepcionTablas');
$r=(object)['numero_interno'=>'REC-CI-0001','proveedor'=>'Proveedor de ejemplo SpA','rut'=>'76.000.000-0','oc'=>'OC-CI-01','bodega'=>'Bodega central','estado'=>'DESCUADRADA','fecha_recepcion'=>'2026-11-25 00:00:00','subtotal_neto'=>'1234567','total_descuentos'=>'0','total_exento'=>'100','total_iva'=>'234567','otros_impuestos'=>'0','total'=>'1469234','observaciones'=>'Observación de prueba: recepción parcial.'];
$doc=(object)['nombre'=>'Factura electrónica','folio'=>'CI-5-2','fecha_documento'=>'2026-11-25','total_informado'=>'1469234'];
$rows=[];
for($i=1;$i<=65;$i++)$rows[]=(object)['numero_linea'=>$i,'sku'=>sprintf('SKU-%03d',$i),'nombre'=>$i===5?str_repeat('Mercadería con descripción extensa y acentos, ',12):'Producto de prueba '.$i,'cantidad_documentada'=>'100.000','cantidad_recibida'=>'99.500','cantidad_aceptada'=>'99.500','cantidad_rechazada'=>'0','costo_unitario_neto'=>'1234.5678','monto_neto'=>'123456','monto_exento'=>'0','monto_iva'=>'23456','total'=>'146912'];
$dif=[(object)['recepcion_id'=>1,'tipo_diferencia'=>'FALTANTE','cantidad_diferencia'=>'0.500','motivo'=>str_repeat('Diferencia física de prueba; ',24),'estado_resolucion'=>'ABIERTA']];
$dir=sys_get_temp_dir().'/v91pdf';
if(!is_dir($dir))mkdir($dir,0777,true);
file_put_contents($dir.'/recepcion-multipagina.pdf',$method->invoke($service,$r,$doc,$rows,$dif));
$r->estado='PENDIENTE';
file_put_contents($dir.'/recepcion-borrador.pdf',$method->invoke($service,$r,$doc,array_slice($rows,0,3),[]));
echo "V91_RENDER_OK\n";
