<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PdfOrdenCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Orden de compra: estados del catálogo único y detalles ligados a producto_id.
 * No ejecuta recepciones ni altera saldos de inventario / CPP.
 */
final class OrdenesCompraControlador extends Controller
{
    private const MAX_LINEAS=100;
    private const IVA_PORCENTAJE=19;

    private function respuesta($data,int $status=200)
    {
        return response()->json($data,$status)->header('Cache-Control','no-store');
    }

    private function estado(string $codigo): int
    {
        $id=DB::table('modulos_estados as me')
            ->join('modulos as m','m.id','=','me.modulo_id')
            ->join('estados as e','e.id','=','me.estado_id')
            ->where('m.codigo','ORDEN_COMPRA')->where('m.activo',1)
            ->where('me.activo',1)->where('e.codigo',$codigo)->where('e.activo',1)
            ->value('e.id');
        abort_unless($id,503,'Catálogo de estados de órdenes de compra no configurado');
        return (int)$id;
    }

    private function base()
    {
        return DB::table('ordenes_compra as oc')
            ->join('proveedores as prov','prov.id','=','oc.proveedor_id')
            ->join('estados as e','e.id','=','oc.estado_id')
            ->select('oc.*','e.nombre as estado_nombre','e.codigo as estado_codigo',
                'prov.nombre as proveedor_nombre','prov.rut as proveedor_rut',
                'prov.correo_electronico as proveedor_correo_electronico',
                'prov.direccion as proveedor_direccion');
    }

    private function documento(int $id): array
    {
        $row=$this->base()->where('oc.id',$id)->first();
        abort_unless($row,404,'Orden de compra no encontrada');
        $data=(array)$row;
        $data['detalles']=DB::table('detalles_ordenes_compra as d')
            ->join('productos as p','p.id','=','d.producto_id')
            ->where('d.orden_compra_id',$id)
            ->orderBy('d.id')
            ->get(['d.id','d.producto_id','p.sku','p.nombre','d.cantidad',
                'd.costo_unitario_neto','d.subtotal_neto'])
            ->map(fn($d)=>(array)$d)->all();
        return $data;
    }

    public function estados()
    {
        return $this->respuesta(['items'=>DB::table('modulos_estados as me')
            ->join('modulos as m','m.id','=','me.modulo_id')
            ->join('estados as e','e.id','=','me.estado_id')
            ->where('m.codigo','ORDEN_COMPRA')->where('me.activo',1)
            ->where('e.activo',1)
            ->orderBy('me.orden')->get(['e.id','e.codigo','e.nombre'])]);
    }

    public function proveedores(Request $request)
    {
        $v=$request->validate([
            'buscar'=>['sometimes','nullable','string','max:120'],
            'page'=>['sometimes','integer','min:1','max:10000']
        ]);
        $q=DB::table('proveedores')->where('activo',1)
            ->select('id','nombre','rut','correo_electronico','direccion');
        if(!empty($v['buscar'])){
            $like='%'.trim($v['buscar']).'%';
            $q->where(fn($s)=>$s->where('nombre','like',$like)
                ->orWhere('rut','like',$like));
        }
        return $this->respuesta($q->orderBy('nombre')->orderBy('id')->paginate(25));
    }

    public function listar(Request $request)
    {
        $v=$request->validate([
            'page'=>['sometimes','integer','min:1','max:10000'],
            'por_pagina'=>['sometimes','integer','min:1','max:25'],
            'buscar'=>['sometimes','nullable','string','max:120'],
            'estado_id'=>['sometimes','nullable','integer','min:1'],
            'proveedor_id'=>['sometimes','nullable','integer','min:1'],
        ]);
        $q=$this->base();
        if(!empty($v['estado_id']))$q->where('oc.estado_id',(int)$v['estado_id']);
        if(!empty($v['proveedor_id']))$q->where('oc.proveedor_id',(int)$v['proveedor_id']);
        if(!empty($v['buscar'])){
            $like='%'.trim($v['buscar']).'%';
            $q->where(fn($query)=>$query->where('oc.numero','like',$like)
                ->orWhere('prov.nombre','like',$like)
                ->orWhere('prov.rut','like',$like));
        }
        return $this->respuesta($q->orderBy('oc.id','desc')
            ->paginate((int)($v['por_pagina']??25)));
    }

    public function ver(int $id)
    {
        return $this->respuesta($this->documento($id));
    }

    private function validar(Request $request): array
    {
        $permitidos=['proveedor_id','solicitada_en','observaciones','detalles'];
        $prohibidos=array_diff(array_keys($request->all()),$permitidos);
        if($prohibidos)throw ValidationException::withMessages([
            'orden'=>['El número, estado, fecha de recepción y totales son de solo lectura.']
        ]);
        return Validator::make($request->all(),[
            'proveedor_id'=>['required','integer','min:1'],
            'solicitada_en'=>['required','date_format:Y-m-d'],
            'observaciones'=>['sometimes','nullable','string','max:5000'],
            'detalles'=>['required','array','min:1','max:'.self::MAX_LINEAS],
            'detalles.*.producto_id'=>['required','integer','min:1','distinct'],
            'detalles.*.cantidad'=>['required','regex:/^(?:[1-9]\d{0,5}|0)(?:\.\d{1,3})?$/'],
            'detalles.*.costo_unitario_neto'=>['required','regex:/^(?:0|[1-9]\d{0,6})(?:\.\d{1,4})?$/'],
        ])->after(function($validator) use ($request): void {
            foreach((array)$request->input('detalles',[]) as $i=>$row){
                if(!is_array($row)||array_diff(array_keys($row),
                    ['producto_id','cantidad','costo_unitario_neto']))
                    $validator->errors()->add('detalles.'.$i,'El detalle contiene campos no autorizados.');
                if(is_array($row)&&isset($row['cantidad'])&&(float)$row['cantidad']<=0)
                    $validator->errors()->add('detalles.'.$i.'.cantidad','Cantidad debe ser positiva.');
                if(is_array($row)&&isset($row['costo_unitario_neto'])&&
                    (float)$row['costo_unitario_neto']<=0)
                    $validator->errors()->add('detalles.'.$i.'.costo_unitario_neto',
                        'Costo neto debe ser positivo.');
            }
        })->validate();
    }

    /** Cotización de impuestos y total sin crear, modificar ni enviar órdenes. */
    public function calcular(Request $request)
    {
        $data=$this->validar($request);
        $existe=DB::table('proveedores')
            ->where('id',(int)$data['proveedor_id'])->where('activo',1)->exists();
        if(!$existe)throw ValidationException::withMessages([
            'proveedor_id'=>['Selecciona un proveedor activo.']
        ]);
        [, $totales]=$this->prepararLineas((int)$data['proveedor_id'],$data['detalles']);
        return $this->respuesta($totales);
    }

    /** Enteros en centavos; la multiplicación decimal la resuelve MariaDB. */
    private function centavos(string $valor): int
    {
        $normalized=str_replace(',','.',trim($valor));
        [$whole,$fraction]=array_pad(explode('.',$normalized,2),2,'');
        $fraction=str_pad(substr($fraction,0,2),2,'0');
        abort_unless(ctype_digit($whole) && ctype_digit($fraction),422,
            'Importe monetario inválido');
        $cents=(int)$whole*100+(int)$fraction;
        abort_if($cents>999999999999,422,'El importe supera el máximo de la orden');
        return $cents;
    }

    private function prepararLineas(int $proveedorId,array $items): array
    {
        $rows=[];$subtotal=0;$impuestos=0;
        foreach($items as $linea){
            $id=(int)$linea['producto_id'];
            $producto=DB::table('productos as p')
                ->join('productos_proveedores as pp',function($join)use($proveedorId){
                    $join->on('pp.producto_id','=','p.id')
                        ->where('pp.proveedor_id','=',$proveedorId)->where('pp.activo','=',1);
                })
                ->leftJoin('condiciones_tributarias as c','c.id','=','p.condicion_tributaria_id')
                ->where('p.id',$id)->where('p.activo',1)
                ->first(['p.id','c.codigo as condicion_codigo']);
            if(!$producto)throw ValidationException::withMessages([
                'detalles'=>['El producto '.$id.' no está activo en el catálogo de este proveedor.']
            ]);
            $cantidad=(string)$linea['cantidad'];
            $costo=(string)$linea['costo_unitario_neto'];
            $importe=DB::selectOne(
                'SELECT CAST(ROUND(CAST(? AS DECIMAL(15,3)) * CAST(? AS DECIMAL(15,4)),2) AS CHAR) AS neto',
                [$cantidad,$costo]
            );
            $cents=$this->centavos((string)$importe->neto);
            abort_if($cents===0,422,'El subtotal de la línea no puede ser cero');
            $subtotal+=$cents;
            if(strtoupper((string)$producto->condicion_codigo)!=='EXENTO'){
                $impuestos+=intdiv($cents*self::IVA_PORCENTAJE+50,100);
            }
            $rows[]=[
                'producto_id'=>$id,'cantidad'=>$cantidad,
                'costo_unitario_neto'=>$costo,
                'subtotal_neto'=>number_format($cents/100,2,'.',''),
            ];
        }
        abort_if($subtotal+$impuestos>999999999999,422,'El total supera el máximo permitido');
        return [$rows,[
            'subtotal_neto'=>number_format($subtotal/100,2,'.',''),
            'total_impuestos'=>number_format($impuestos/100,2,'.',''),
            'total_bruto'=>number_format(($subtotal+$impuestos)/100,2,'.',''),
        ]];
    }

    private function guardar(Request $request,?int $id)
    {
        $data=$this->validar($request);
        $proveedor=DB::table('proveedores')->where('id',(int)$data['proveedor_id'])
            ->where('activo',1)->first();
        if(!$proveedor)throw ValidationException::withMessages([
            'proveedor_id'=>['Selecciona un proveedor activo.']
        ]);
        // No permitir crear una OC aparentemente enviada si no hay destino SMTP.
        if(!$id){
            if(!filter_var((string)$proveedor->correo_electronico,FILTER_VALIDATE_EMAIL))
                throw ValidationException::withMessages([
                    'proveedor_id'=>['El proveedor necesita un correo electrónico válido.']
                ]);
            abort_unless(config('mail.default')==='smtp',503,'Correo saliente no configurado');
        }
        $new=$id===null;
        $orderId=DB::transaction(function()use($data,$id,$new): int {
            if($id!==null){
                $actual=DB::table('ordenes_compra')->where('id',$id)->lockForUpdate()->first();
                abort_unless($actual,404,'Orden no encontrada');
                abort_unless((int)$actual->estado_id===$this->estado('ACTIVA'),409,
                    'Solo se permite editar órdenes activas');
            }
            [$detalles,$totales]=$this->prepararLineas((int)$data['proveedor_id'],$data['detalles']);
            $base=array_merge([
                'proveedor_id'=>(int)$data['proveedor_id'],
                'solicitada_en'=>$data['solicitada_en'].' 00:00:00',
                'observaciones'=>$data['observaciones']??null,
            ],$totales);
            if($new){
                $base+=['numero'=>'PEND-'.bin2hex(random_bytes(12)),
                    'estado'=>'activa','estado_id'=>$this->estado('ACTIVA'),
                    'creado_por'=>request()->user()?->id];
                $id=(int)DB::table('ordenes_compra')->insertGetId($base);
                $numero='OC-'.date('Y',strtotime($data['solicitada_en'])).
                    '-'.str_pad((string)$id,7,'0',STR_PAD_LEFT);
                DB::table('ordenes_compra')->where('id',$id)->update(['numero'=>$numero]);
            }else{
                // El estado y los datos de recepción nunca forman parte de la edición.
                DB::table('ordenes_compra')->where('id',$id)->update($base);
                DB::table('detalles_ordenes_compra')->where('orden_compra_id',$id)->delete();
            }
            foreach($detalles as $detalle){
                DB::table('detalles_ordenes_compra')->insert(
                    array_merge(['orden_compra_id'=>$id],$detalle));
            }
            // Verificación dentro de la misma transacción: un importe ausente o
            // truncado impide confirmar la OC, evitando errores silenciosos.
            $persistido=DB::table('ordenes_compra')->where('id',$id)
                ->first(['subtotal_neto','total_impuestos','total_bruto']);
            foreach($totales as $campo=>$importe){
                if((string)$persistido->{$campo}!==$importe)
                    throw new \RuntimeException('No se pudo persistir '.$campo.' de la orden');
            }
            return $id;
        },3);

        $documento=$this->documento($orderId);
        $response=['id'=>$orderId,'numero'=>$documento['numero'],
            'estado_nombre'=>$documento['estado_nombre'],
            'subtotal_neto'=>$documento['subtotal_neto'],
            'total_impuestos'=>$documento['total_impuestos'],
            'total_bruto'=>$documento['total_bruto'],
            'correo_enviado'=>null,
            'mensaje'=>$new?'Orden de compra creada.':'Orden de compra actualizada.'];
        if($new){
            // DB ya COMMIT: un fallo SMTP NO revierte la orden ni provoca un
            // segundo INSERT al reintentar desde la interfaz. Informar al usuario.
            try{
                $this->enviarProveedor($documento);
                $response['correo_enviado']=true;
                $response['mensaje']='Orden creada y enviada al correo del proveedor.';
            }catch(\Throwable $exception){
                report(new \RuntimeException(
                    'No se pudo enviar el aviso de una orden de compra ya confirmada'));
                $response['correo_enviado']=false;
                $response['mensaje']='Orden guardada, pero el correo no pudo enviarse. '.
                    'Utiliza la acción Reenviar aviso sin crear otra orden.';
            }
        }
        return $this->respuesta($response,$new?201:200);
    }

    private function enviarProveedor(array $documento): void
    {
        abort_unless(config('mail.default')==='smtp',503,'Correo saliente no configurado');
        $destino=trim((string)$documento['proveedor_correo_electronico']);
        abort_unless(filter_var($destino,FILTER_VALIDATE_EMAIL),422,
            'Correo electrónico del proveedor inválido');
        $numero=(string)$documento['numero'];
        $safe=htmlspecialchars($numero,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $proveedor=htmlspecialchars((string)$documento['proveedor_nombre'],
            ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $total='$ '.number_format((float)$documento['total_bruto'],2,',','.');
        $html='<!doctype html><html lang="es"><body style="margin:0;padding:28px;background:#f3f0f8;font-family:Arial,sans-serif">'.
            '<table role="presentation" style="width:100%;max-width:650px;margin:auto;background:white;border-radius:15px;overflow:hidden;border-collapse:collapse">'.
            '<tr><td style="background:#351165;color:white;padding:24px 28px"><b style="font-size:23px">H&D</b><span style="padding-left:14px">RUTA H&D2 · COMPRAS</span></td></tr>'.
            '<tr><td style="padding:30px"><p style="font-size:12px;letter-spacing:2px;color:#6e528b">ORDEN DE COMPRA GENERADA</p>'.
            '<h1 style="font-size:23px;color:#2d1450">'.$safe.'</h1>'.
            '<p style="color:#3b3344">Estimado equipo de <b>'.$proveedor.'</b>:</p>'.
            '<p style="line-height:1.6;color:#3b3344">Se ha generado una nueva orden de compra para su empresa. Adjuntamos el documento PDF con el detalle de productos, cantidades, valores y condiciones.</p>'.
            '<div style="padding:19px;background:#f5f0fb;border-left:4px solid #6c35b2;border-radius:7px">'.
            '<b>Estado:</b> Activa<br><b>Total bruto:</b> '.$total.'</div>'.
            '<p style="font-size:13px;color:#625b6a;margin-top:25px">Por favor, revise el PDF adjunto. Este es un aviso generado por Ruta H&D2.</p></td></tr>'.
            '<tr><td style="padding:17px 28px;background:#f0edf5;color:#63566d;font-size:12px">Comercializadora H&D · Comunicación automática</td></tr>'.
            '</table></body></html>';
        $pdf=(new PdfOrdenCompra())->generar($documento);
        Mail::html($html,static function($mail)use($destino,$numero,$pdf): void {
            $mail->to($destino)->subject('H&D · Orden de compra '.$numero)
                ->attachData($pdf,$numero.'.pdf',['mime'=>'application/pdf']);
        });
    }

    public function crear(Request $request)
    {
        return $this->guardar($request,null);
    }

    public function actualizar(Request $request,int $id)
    {
        return $this->guardar($request,$id);
    }

    public function anular(int $id)
    {
        DB::transaction(function() use ($id): void {
            $row=DB::table('ordenes_compra')->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404,'Orden de compra no encontrada');
            abort_unless((int)$row->estado_id===$this->estado('ACTIVA'),409,
                'Solo pueden anularse órdenes activas; Recepciones se controlará aparte');
            DB::table('ordenes_compra')->where('id',$id)->update([
                'estado_id'=>$this->estado('NULA'),'estado'=>'nula'
            ]);
        });
        return $this->respuesta(['id'=>$id,'estado'=>'NULA',
            'mensaje'=>'Orden anulada. No se modificó el inventario.']);
    }

    public function reenviar(Request $request,int $id)
    {
        $documento=$this->documento($id);
        abort_unless($documento['estado_codigo']==='ACTIVA',409,
            'Solo se reenvían órdenes activas');
        // Límite individual, sin compartir contador con otras operaciones CRUD.
        $key='oc-reenvio:'.$id.':'.(int)($request->user()?->id ?? 0);
        abort_if(RateLimiter::tooManyAttempts($key,4),429,
            'Demasiados reenvíos de esta orden. Inténtalo más tarde.');
        RateLimiter::hit($key,60);
        $this->enviarProveedor($documento);
        return $this->respuesta(['mensaje'=>'Aviso y PDF enviados nuevamente al proveedor.']);
    }

    public function pdf(int $id)
    {
        $documento=$this->documento($id);
        $pdf=(new PdfOrdenCompra())->generar($documento);
        return response($pdf,200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'inline; filename="'.$documento['numero'].'.pdf"',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
