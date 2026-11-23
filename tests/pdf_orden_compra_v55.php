<?php
declare(strict_types=1);
require __DIR__.'/../app/Services/PdfOrdenCompra.php';
use App\Services\PdfOrdenCompra;
$rows=[];
for($i=1;$i<=24;$i++){
    $rows[]=[
        'sku'=>'SKU-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),
        'nombre'=>'Producto de prueba '.$i,
        'cantidad'=>'2.500','costo_unitario_neto'=>'1190.0000',
        'subtotal_neto'=>'2975.00'
    ];
}
$doc=[
 'emisor'=>[
   'nombre'=>'Emisor de Prueba H&D',
   'rut'=>'11.111.111-1',
   'giro'=>'Comercializacion de productos',
   'direccion'=>'Direccion de muestra 123',
   'correo'=>'emisor@example.invalid',
 ],
 'creado_en'=>'2026-11-23 11:24:00',
 'numero'=>'OC-2026-000001','estado_nombre'=>'Activa',
 'proveedor_nombre'=>'Proveedor Comercial de Prueba',
 'proveedor_rut'=>'12.345.678-5','proveedor_correo_electronico'=>'prueba@example.invalid',
 'proveedor_direccion'=>'Dirección sintética de prueba',
 'solicitada_en'=>'2026-11-23','observaciones'=>'Documento sin datos reales',
 'subtotal_neto'=>'71400.00','total_impuestos'=>'13566.00',
 'total_bruto'=>'84966.00','detalles'=>$rows
];
$pdf=(new PdfOrdenCompra())->generar($doc);
if(!str_starts_with($pdf,'%PDF-1.4'))throw new RuntimeException('No PDF header');
if(!str_contains($pdf,'/Count 2'))throw new RuntimeException('No pages expected');
if(!str_contains($pdf,'/Type /Catalog'))throw new RuntimeException('No Catalog');
if(!str_contains($pdf,'startxref'))throw new RuntimeException('No xref');
foreach([
 '0.15 0.12 0.22 rg BT /F2 9 Tf 55.00 705.00 Td (EMISOR)' => 'Emisor oscuro visible',
 '0.15 0.12 0.22 rg BT /F2 9 Tf 315.00 705.00 Td (PROVEEDOR)' => 'Proveedor oscuro visible',
 '(Emision: 23-11-2026)' => 'Fecha de emision',
 '(Fecha solicitada: 23-11-2026)' => 'Fecha solicitada',
 '(Emisor de Prueba H&D)' => 'Razon de emisor',
 '(Proveedor Comercial de Prueba)' => 'Proveedor',
 '(RUT: 11.111.111-1)' => 'RUT emisor',
 '(RUT: 12.345.678-5)' => 'RUT proveedor',
 '(Correo: emisor@example.invalid)' => 'Correo emisor',
 '(Correo: prueba@example.invalid)' => 'Correo proveedor'
] as $fragment=>$etiqueta){
    if(!str_contains($pdf,$fragment))throw new RuntimeException('PDF omite '.$etiqueta);
}
if(substr_count($pdf,'(EMISOR)')!==2 || substr_count($pdf,'(PROVEEDOR)')!==2)
    throw new RuntimeException('El encabezado debe repetirse en las dos paginas');
file_put_contents('/tmp/rutahd_v55_oc_test.pdf',$pdf);
echo "V55_PDF_OK pages=2 size=".strlen($pdf)."\n";
