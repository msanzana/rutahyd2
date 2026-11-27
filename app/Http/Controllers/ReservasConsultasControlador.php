<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** V109E5A: listado y ficha documental; no acreditan disponibilidad para operar. */
final class ReservasConsultasControlador extends Controller
{
    private function base(int $sid){
        return DB::table('inventario_reservas as r')
            ->where('r.sucursal_id',$sid)
            ->whereIn('r.estado',['PENDIENTE','ACTIVA','PARCIAL','CONSUMIDA','LIBERADA'])
            ->whereExists(fn($q)=>$q->selectRaw('1')->from('inventario_reservas_detalle as d')->whereColumn('d.reserva_id','r.id'))
            // No exponer una cabecera si alguna línea pertenece a una bodega sin atribución.
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('inventario_reservas_detalle as d')->whereColumn('d.reserva_id','r.id')
                ->whereNotExists(fn($b)=>$b->selectRaw('1')->from('inventario_bodegas_sucursales as bs')->whereColumn('bs.bodega_id','d.bodega_id')->where('bs.sucursal_id',$sid)))
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('inventario_reservas_operaciones as x')
                ->leftJoin('inventario_operaciones as o','o.id','=','x.operacion_id')->whereColumn('x.reserva_id','r.id')
                ->where(fn($a)=>$a->whereNull('o.id')->orWhereNull('o.sucursal_id')->orWhere('o.sucursal_id','<>',$sid)->orWhereNull('o.clase')->orWhere('o.clase','<>','RESERVA')))
            ->select('r.id','r.sucursal_id','r.numero_interno','r.nota_venta_id','r.estado','r.vence_en','r.creado_en','r.creado_por');
    }
    private function vencida(object $h): bool {
        return in_array($h->estado,['ACTIVA','PARCIAL'],true)&&$h->vence_en!==null&&(string)$h->vence_en<=now()->format('Y-m-d H:i:s');
    }
    public function listar(Request $r){
        $v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1','estado'=>'nullable|in:PENDIENTE,ACTIVA,PARCIAL,CONSUMIDA,LIBERADA','bodega_id'=>'nullable|integer|min:1','nota_venta_id'=>'nullable|integer|min:1']);
        return response()->json(DB::transaction(function()use($r,$v){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;
            $q=$this->base($sid);$s=trim($v['buscar']??'');
            if($s!=='')$q->where(fn($x)=>$x->where('r.numero_interno','like','%'.$s.'%')->orWhereExists(fn($z)=>$z->selectRaw('1')->from('inventario_reservas_detalle as d')->join('productos as p','p.id','=','d.producto_id')->whereColumn('d.reserva_id','r.id')->where(fn($p)=>$p->where('p.sku','like','%'.$s.'%')->orWhere('p.nombre','like','%'.$s.'%'))));
            if(isset($v['estado']))$q->where('r.estado',$v['estado']);
            if(isset($v['nota_venta_id']))$q->where('r.nota_venta_id',$v['nota_venta_id']);
            if(isset($v['bodega_id']))$q->whereExists(fn($x)=>$x->selectRaw('1')->from('inventario_reservas_detalle as d')->whereColumn('d.reserva_id','r.id')->where('d.bodega_id',$v['bodega_id']));
            $p=$q->orderByDesc('r.id')->paginate(25,['*'],'page',(int)($v['page']??1));
            $p->getCollection()->transform(function($h){$h->vencimiento_alcanzado=$this->vencida($h);return $h;});
            return $p->toArray()+['escrituras_habilitadas'=>false,'solo_consulta'=>true];
        },3))->header('Cache-Control','private, no-store');
    }
    private function datos(Request $r,int $id): array {
        return DB::transaction(function()use($r,$id){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;
            $h=$this->base($sid)->where('r.id',$id)->first();abort_unless($h,404,'Reserva no disponible.');
            $h->vencimiento_alcanzado=$this->vencida($h);
            $rows=DB::table('inventario_reservas_detalle as d')->join('productos as p','p.id','=','d.producto_id')->join('bodegas as b','b.id','=','d.bodega_id')->where('d.reserva_id',$id)->orderBy('d.id')->select('d.id','d.producto_id','p.sku','p.nombre as producto','d.bodega_id','b.nombre as bodega','d.cantidad_reservada','d.cantidad_liberada','d.cantidad_despachada')->get();
            abort_unless($rows->count()===DB::table('inventario_reservas_detalle')->where('reserva_id',$id)->count(),409,'Detalle documental incompleto.');
            $seen=[];
            $lines=$rows->map(function($l)use($h,&$seen){
                $key=$l->producto_id.':'.$l->bodega_id;
                abort_if(isset($seen[$key]),409,'Detalle duplicado.');$seen[$key]=true;
                $q=BigDecimal::of((string)$l->cantidad_reservada)->toScale(8);$a=BigDecimal::of((string)$l->cantidad_liberada)->toScale(8);$d=BigDecimal::of((string)$l->cantidad_despachada)->toScale(8);
                $p=$q->minus($a)->minus($d);
                abort_unless($q->isPositive()&&!$a->isNegative()&&!$d->isNegative()&&!$p->isNegative(),409,'Cantidades documentales inconsistentes.');
                if($h->estado==='PENDIENTE')abort_unless($a->isZero()&&$d->isZero(),409,'Solicitud pendiente con movimientos.');
                if(in_array($h->estado,['CONSUMIDA','LIBERADA'],true))abort_unless($p->isZero(),409,'Reserva cerrada con saldo pendiente.');
                $l->cantidad_reservada=(string)$q;$l->cantidad_liberada=(string)$a;$l->cantidad_despachada=(string)$d;
                $l->cantidad_solicitada_pendiente=$h->estado==='PENDIENTE'?(string)$q:'0.00000000';
                $l->saldo_documental=$h->estado==='PENDIENTE'?'0.00000000':(string)$p;
                return $l;
            });
            $ops=DB::table('inventario_reservas_operaciones as x')->join('inventario_operaciones as o','o.id','=','x.operacion_id')->join('estados as e','e.id','=','o.estado_id')
                ->where('x.reserva_id',$id)->where('o.sucursal_id',$sid)->where('o.clase','RESERVA')->whereIn('o.motivo_codigo_snapshot',[710,711,712])->where('e.codigo','<>','INV_ANULADA')
                ->select('o.id','o.numero_interno','o.motivo_codigo_snapshot','o.motivo_nombre_snapshot','e.codigo as estado','o.version','o.creado_en','o.aprobado_en','o.confirmado_en')->orderBy('o.id')->get();
            return ['reserva'=>$h,'detalles'=>$lines,'operaciones'=>$ops,'escrituras_habilitadas'=>false,'solo_consulta'=>true,'saldo_acreditado'=>false];
        },3);
    }
    public function ver(Request $r,int $id){
        return response()->json($this->datos($r,$id))->header('Cache-Control','private, no-store');
    }
    public function pdf(Request $r,int $id){
        $data=$this->datos($r,$id);
        $data['generado_en']=now()->format('Y-m-d H:i:s').' '.config('app.timezone');
        $pdf=(new \App\Services\ComprobanteReservaPdf())->generar($data);
        return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="reserva-'.$id.'.pdf"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
