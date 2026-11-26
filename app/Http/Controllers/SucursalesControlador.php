<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SucursalesControlador extends Controller
{
    private function admin(Request $r): int {
        $u=$r->user();abort_unless($u&&$u->activo&&$u->rol?->codigo==='administrador',403,'Solo Administración puede configurar sucursales.');
        return (int)$u->id;
    }
    private function audit(int $user,string $action,int $id,$before,$after): void {
        DB::table('registros_auditoria')->insert(['usuario_id'=>$user,'accion'=>$action,'tipo_entidad'=>'sucursales','entidad_id'=>$id,'valores_anteriores'=>json_encode($before,JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode($after,JSON_THROW_ON_ERROR)]);
    }
    public function listar(Request $r) {
        $this->admin($r);$v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        $q=DB::table('sucursales as s')->leftJoin('ciudades as c','c.id','=','s.ciudad_id')->select('s.*','c.nombre as ciudad')
          ->selectSub(DB::table('inventario_bodegas_sucursales as bs')->selectRaw('COUNT(*)')->whereColumn('bs.sucursal_id','s.id'),'bodegas_count')
          ->selectSub(DB::table('usuarios_sucursales as us')->selectRaw('COUNT(*)')->whereColumn('us.sucursal_id','s.id')->where('us.activo',1),'usuarios_count');
        if(!empty($v['buscar'])){$term=trim($v['buscar']);$q->where(fn($x)=>$x->where('s.codigo','like','%'.$term.'%')->orWhere('s.nombre','like','%'.$term.'%')->orWhere('c.nombre','like','%'.$term.'%'));}
        return response()->json($q->orderBy('s.nombre')->orderBy('s.id')->paginate(25))->header('Cache-Control','no-store');
    }
    public function crear(Request $r){return $this->guardar($r,null);}
    public function actualizar(Request $r,int $id){return $this->guardar($r,$id);}
    private function guardar(Request $r,?int $id){
        $user=$this->admin($r);
        $v=$r->validate(['codigo'=>$id?['prohibited']:['required','string','max:30','regex:/^[A-Z0-9][A-Z0-9_-]*$/D',Rule::unique('sucursales','codigo')],'nombre'=>'required|string|max:130','ciudad_id'=>'nullable|integer|min:1']);
        $name=trim($v['nombre']);abort_if($name==='',422,'Ingrese el nombre de la sucursal.');
        return response()->json(DB::transaction(function()use($v,$id,$user,$name){
            $city=$v['ciudad_id']??null;
            if($city!==null)abort_unless(DB::table('ciudades')->where('id',$city)->where('activo',1)->exists(),422,'Ciudad no disponible.');
            $fields=['nombre'=>$name,'ciudad_id'=>$city];
            if($id===null){
                $id=DB::table('sucursales')->insertGetId($fields+['codigo'=>$v['codigo'],'activo'=>1]);
                $this->audit($user,'SUCURSAL_CREAR',(int)$id,null,$fields+['codigo'=>$v['codigo']]);
            }else{
                $old=DB::table('sucursales')->where('id',$id)->lockForUpdate()->first();abort_unless($old,404);
                DB::table('sucursales')->where('id',$id)->update($fields);$this->audit($user,'SUCURSAL_EDITAR',$id,$old,$fields);
            }
            return DB::table('sucursales')->where('id',$id)->first();
        },3),$id===null?201:200);
    }
    public function estado(Request $r,int $id){
        $user=$this->admin($r);$v=$r->validate(['activo'=>'required|boolean']);
        return response()->json(DB::transaction(function()use($id,$user,$v){
            $old=DB::table('sucursales')->where('id',$id)->lockForUpdate()->first();abort_unless($old,404);
            if(!$v['activo']){
                abort_if(DB::table('inventario_bodegas_sucursales')->where('sucursal_id',$id)->exists(),409,'Sucursal con bodegas asignadas; cierre operativo especializado pendiente.');
                abort_if(DB::table('usuarios_sucursales')->where('sucursal_id',$id)->where('activo',1)->exists(),409,'Revise los accesos activos primero.');
                abort_if(DB::table('sesiones_sucursales')->where('sucursal_id',$id)->exists(),409,'Sucursal con sesiones registradas.');
                foreach(['ordenes_compra','compras','recepciones','recepciones_consolidadas','ventas','notas_venta','kardex','inventario_operaciones','inventario_conteos','inventario_reservas'] as $t)
                    abort_if(DB::table($t)->where('sucursal_id',$id)->exists(),409,'Sucursal con historial; cierre operativo especializado pendiente.');
                foreach(['guias_despacho','inventario_traspasos'] as $t)
                    abort_if(DB::table($t)->where(fn($q)=>$q->where('sucursal_origen_id',$id)->orWhere('sucursal_destino_id',$id))->exists(),409,'Sucursal con traspasos o guías históricos.');
            }
            DB::table('sucursales')->where('id',$id)->update(['activo'=>(int)$v['activo']]);$this->audit($user,'SUCURSAL_ESTADO',$id,['activo'=>$old->activo],['activo'=>(int)$v['activo']]);return ['id'=>$id,'activo'=>(int)$v['activo']];
        },3));
    }
    public function bodegas(Request $r,int $id){
        $this->admin($r);abort_unless(DB::table('sucursales')->where('id',$id)->exists(),404);
        $v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        $q=DB::table('bodegas as b')->leftJoin('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->leftJoin('sucursales as s','s.id','=','bs.sucursal_id')->select('b.id','b.codigo','b.nombre','b.activo','bs.sucursal_id','s.nombre as sucursal');
        if(!empty($v['buscar'])){$term=trim($v['buscar']);$q->where(fn($x)=>$x->where('b.nombre','like','%'.$term.'%')->orWhere('b.codigo','like','%'.$term.'%'));}
        return response()->json($q->orderBy('b.nombre')->orderBy('b.id')->paginate(25));
    }
    public function asignarBodega(Request $r,int $id,int $bodega){
        $user=$this->admin($r);
        return response()->json(DB::transaction(function()use($id,$bodega,$user){
            abort_unless(DB::table('sucursales')->where('id',$id)->where('activo',1)->lockForUpdate()->first(),409,'Sucursal no activa.');
            abort_unless(DB::table('bodegas')->where('id',$bodega)->where('activo',1)->lockForUpdate()->first(),409,'Bodega no activa.');
            $old=DB::table('inventario_bodegas_sucursales')->where('bodega_id',$bodega)->lockForUpdate()->first();
            if($old){abort_unless((int)$old->sucursal_id===$id,409,'Bodega ya asociada. No se puede reasignar ni reescribir el historial desde este formulario.');return ['bodega_id'=>$bodega,'sucursal_id'=>$id];}
            DB::table('inventario_bodegas_sucursales')->insert(['bodega_id'=>$bodega,'sucursal_id'=>$id]);
            $this->audit($user,'SUCURSAL_ASIGNAR_BODEGA',$id,null,['bodega_id'=>$bodega,'sucursal_id'=>$id,'historico_sin_modificar'=>true]);
            return ['bodega_id'=>$bodega,'sucursal_id'=>$id];
        },3));
    }
    public function usuarios(Request $r,int $id){
        $this->admin($r);abort_unless(DB::table('sucursales')->where('id',$id)->exists(),404);
        $v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        $q=DB::table('usuarios as u')->join('roles as r','r.id','=','u.rol_id')->leftJoin('usuarios_sucursales as us',fn($j)=>$j->on('us.usuario_id','=','u.id')->where('us.sucursal_id',$id))->select('u.id','u.nombre','u.activo','r.codigo as rol','r.nombre as perfil','us.activo as asignado');
        if(!empty($v['buscar']))$q->where('u.nombre','like','%'.trim($v['buscar']).'%');
        return response()->json($q->orderBy('u.nombre')->orderBy('u.id')->paginate(25));
    }
    public function acceso(Request $r,int $id,int $usuario){
        $user=$this->admin($r);$v=$r->validate(['activo'=>'required|boolean']);
        return response()->json(DB::transaction(function()use($id,$usuario,$user,$v){
            $s=DB::table('sucursales')->where('id',$id)->lockForUpdate()->first();abort_unless($s,404);
            $u=DB::table('usuarios as u')->join('roles as r','r.id','=','u.rol_id')->where('u.id',$usuario)->select('u.id','u.activo','r.codigo as rol')->lockForUpdate()->first();abort_unless($u,404);
            abort_if($u->rol==='administrador',409,'El administrador general accede a las sucursales activas por su perfil.');
            abort_if($v['activo']&&(!$s->activo||!$u->activo),409,'Usuario o sucursal inactivos.');
            $old=DB::table('usuarios_sucursales')->where('usuario_id',$usuario)->where('sucursal_id',$id)->lockForUpdate()->first();
            if($old)DB::table('usuarios_sucursales')->where('usuario_id',$usuario)->where('sucursal_id',$id)->update(['activo'=>(int)$v['activo'],'asignado_por'=>$user,'asignado_en'=>now()]);
            elseif($v['activo'])DB::table('usuarios_sucursales')->insert(['usuario_id'=>$usuario,'sucursal_id'=>$id,'activo'=>1,'asignado_por'=>$user]);
            if(!$v['activo'])DB::table('sesiones_sucursales')->where('sucursal_id',$id)->whereIn('token_acceso_id',DB::table('tokens_acceso')->where('usuario_id',$usuario)->select('id'))->delete();
            $this->audit($user,'SUCURSAL_ACCESO',$id,['usuario_id'=>$usuario,'activo'=>(int)($old->activo??0)],['usuario_id'=>$usuario,'activo'=>(int)$v['activo']]);
            return ['usuario_id'=>$usuario,'sucursal_id'=>$id,'activo'=>(int)$v['activo']];
        },3));
    }
}
