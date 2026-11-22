<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Ruta H&D V38: geografía normalizada; ciudad_id como referencia de domicilio.
 * CRUD de ubicación exclusivamente para administradores, consulta JWT autorizada.
 * No utiliza Laravel migrations ni modifica inventario.
 */
final class UbicacionesControlador extends Controller
{
    private const TABLAS=[
        'paises'=>['padre'=>null,'codigo'=>'codigo_iso2'],
        'regiones'=>['padre'=>'paises','fk'=>'pais_id','codigo'=>'codigo_cut'],
        'provincias'=>['padre'=>'regiones','fk'=>'region_id','codigo'=>'codigo_cut'],
        'comunas'=>['padre'=>'provincias','fk'=>'provincia_id','codigo'=>'codigo_cut'],
        'ciudades'=>['padre'=>'comunas','fk'=>'comuna_id','codigo'=>null],
    ];

    private function tabla(string $tipo): array
    {
        abort_unless(isset(self::TABLAS[$tipo]),404,'Ubicación no disponible');
        return self::TABLAS[$tipo];
    }

    private function permitir(Request $request,bool $escritura): void
    {
        $user=$request->user();
        abort_unless($user && $user->activo,401);
        if($user->rol?->codigo==='administrador')return;
        abort_if($escritura,403,'Solo Administración gestiona ubicaciones');
        $available=DB::table('roles_permisos as rp')
            ->join('permisos as p','p.id','=','rp.permiso_id')
            ->where('rp.rol_id',$user->rol_id)
            ->whereIn('p.codigo',['compras.gestionar','clientes.gestionar','usuarios.gestionar',
                'inventario.gestionar'])->exists();
        abort_unless($available,403,'No tienes permiso para consultar ubicaciones');
    }

    private function response(string $tipo,object $row): array
    {
        $c=self::TABLAS[$tipo];
        return [
            'id'=>(int)$row->id,
            'nombre'=>$row->nombre,
            'codigo'=>$c['codigo'] ? ($row->{$c['codigo']}??null) : null,
            'parentId'=>isset($c['fk']) ? (int)$row->{$c['fk']} : null,
            'activo'=>(bool)$row->activo,
            'tipoLocalidad'=>$tipo==='ciudades' ? $row->tipo_localidad : null,
            'fuente'=>$tipo==='ciudades' ? $row->fuente : null,
        ];
    }

    public function listar(Request $request,string $tipo)
    {
        $config=$this->tabla($tipo);
        $this->permitir($request,false);
        $input=$request->validate([
            'padre_id'=>['sometimes','nullable','integer','min:1'],
            'buscar'=>['sometimes','nullable','string','max:120'],
            'page'=>['sometimes','integer','min:1','max:10000'],
            'por_pagina'=>['sometimes','integer','min:1','max:100'],
            'incluye_inactivos'=>['sometimes','boolean'],
        ]);
        $query=DB::table($tipo);
        if(isset($input['padre_id'])){
            abort_unless(isset($config['fk']),422,'Esta ubicación no tiene padre');
            $query->where($config['fk'],$input['padre_id']);
        }
        if(!($input['incluye_inactivos']??false))$query->where('activo',1);
        if(!empty($input['buscar']))$query->where('nombre','like','%'.trim($input['buscar']).'%');
        $page=$query->orderBy('nombre')->orderBy('id')->paginate((int)($input['por_pagina']??25));
        $page->getCollection()->transform(fn(object $x)=>$this->response($tipo,$x));
        return response()->json($page)->header('Cache-Control','no-store');
    }

    public function jerarquia(Request $request,int $ciudadId)
    {
        $this->permitir($request,false);
        $row=DB::table('ciudades as c')
            ->join('comunas as co','co.id','=','c.comuna_id')
            ->join('provincias as pr','pr.id','=','co.provincia_id')
            ->join('regiones as re','re.id','=','pr.region_id')
            ->join('paises as pa','pa.id','=','re.pais_id')
            ->where('c.id',$ciudadId)
            ->first([
                'pa.id as paisId','pa.nombre as pais','pa.codigo_iso2 as paisCodigo',
                're.id as regionId','re.nombre as region','re.codigo_cut as regionCodigo',
                'pr.id as provinciaId','pr.nombre as provincia','pr.codigo_cut as provinciaCodigo',
                'co.id as comunaId','co.nombre as comuna','co.codigo_cut as comunaCodigo',
                'c.id as ciudadId','c.nombre as ciudad','c.tipo_localidad as tipoLocalidad',
            ]);
        abort_unless($row,404,'Ciudad no registrada');
        return response()->json($row)->header('Cache-Control','no-store');
    }

    private function guardar(Request $request,string $tipo,?int $id): object
    {
        $cfg=$this->tabla($tipo);
        $this->permitir($request,true);
        $new=$id===null;
        $allow=['nombre','activo','codigo','parentId','tipoLocalidad'];
        abort_if(array_diff(array_keys($request->all()),$allow)!==[],422,
            'Campos no permitidos en ubicación');
        $data=Validator::make($request->all(),[
            'nombre'=>['required','string','min:2','max:160'],
            'codigo'=>[$cfg['codigo']?'required':'prohibited','nullable','string','max:6'],
            'parentId'=>[$cfg['padre']?'required':'prohibited','nullable','integer','min:1'],
            'tipoLocalidad'=>[$tipo==='ciudades'?'sometimes':'prohibited',
                'nullable','string','max:36'],
            'activo'=>['sometimes','boolean'],
        ])->validate();
        $db=['nombre'=>trim($data['nombre']),'activo'=>$data['activo']??true];
        if($cfg['codigo']){
            $code=strtoupper(trim($data['codigo']));
            $pattern=$tipo==='paises'?'/^[A-Z]{2}$/D':
                ($tipo==='regiones'?'/^[0-9]{2}$/D':
                 ($tipo==='provincias'?'/^[0-9]{3}$/D':'/^[0-9]{5}$/D'));
            abort_unless(preg_match($pattern,$code)===1,422,'Código territorial inválido');
            $db[$cfg['codigo']]=$code;
        }
        if($cfg['padre']){
            $parent=DB::table($cfg['padre'])->where('id',$data['parentId'])
                ->where('activo',1)->first();
            abort_unless($parent,422,'Ubicación superior inexistente o inactiva');
            $db[$cfg['fk']]=(int)$parent->id;
            // En CUT 5 dígitos, la comuna comienza por los 3 de su provincia,
            // provincia por los 2 de su región; región enlaza país directamente.
            if($tipo==='provincias')abort_unless(str_starts_with($db['codigo_cut'],
                $parent->codigo_cut),422,'Provincia no pertenece al CUT de la región');
            if($tipo==='comunas')abort_unless(str_starts_with($db['codigo_cut'],
                $parent->codigo_cut),422,'Comuna no pertenece al CUT de la provincia');
        }
        if($tipo==='ciudades'){
            $db['tipo_localidad']=trim($data['tipoLocalidad']??'CIUDAD')?:'CIUDAD';
            // Conservar la procedencia oficial/registrada de las filas importadas.
            if($new)$db['fuente']='Mantenedor Ruta H&D (revisión manual)';
        }
        return DB::transaction(function()use($tipo,$id,$db,$new){
            if(!$new){
                $original=DB::table($tipo)->where('id',$id)->lockForUpdate()->first();
                abort_unless($original,404,'Ubicación no encontrada');
                if($tipo==='paises'||$tipo==='regiones'||$tipo==='provincias'||$tipo==='comunas'){
                    $code=self::TABLAS[$tipo]['codigo'];
                    abort_if($original->{$code}!==$db[$code],422,
                        'No se modifica el código territorial de un registro existente');
                }
                if(isset(self::TABLAS[$tipo]['fk'])){
                    $fk=self::TABLAS[$tipo]['fk'];
                    abort_if((int)$original->{$fk}!==$db[$fk],422,
                        'No se cambia el padre de una ubicación ya registrada');
                }
                if((bool)$original->activo && !$db['activo'])$this->validarDesactivacion($tipo,$id);
                DB::table($tipo)->where('id',$id)->update($db);
            }else $id=(int)DB::table($tipo)->insertGetId($db);
            return DB::table($tipo)->where('id',$id)->first();
        });
    }

    public function crear(Request $request,string $tipo)
    {
        try{
            $item=$this->guardar($request,$tipo,null);
            return response()->json($this->response($tipo,$item),201)->header('Cache-Control','no-store');
        }catch(QueryException $e){
            if((string)$e->getCode()==='23000')abort(409,'Código o localidad duplicada');
            throw $e;
        }
    }
    public function actualizar(Request $request,string $tipo,int $id)
    {
        try{
            return response()->json($this->response($tipo,$this->guardar($request,$tipo,$id)))
                ->header('Cache-Control','no-store');
        }catch(QueryException $e){
            if((string)$e->getCode()==='23000')abort(409,'Código o localidad duplicada');
            throw $e;
        }
    }

    private function validarDesactivacion(string $tipo,int $id): void
    {
        $children=['paises'=>['regiones','pais_id'],'regiones'=>['provincias','region_id'],
            'provincias'=>['comunas','provincia_id'],'comunas'=>['ciudades','comuna_id']];
        if(isset($children[$tipo])){
            [$table,$foreign]=$children[$tipo];
            abort_if(DB::table($table)->where($foreign,$id)->exists(),409,
                'La ubicación tiene subdivisiones vinculadas');
            return;
        }
        foreach(['proveedores','clientes','usuarios','bodegas','empresas'] as $table){
            if(Schema::hasTable($table) && Schema::hasColumn($table,'ciudad_id') &&
                DB::table($table)->where('ciudad_id',$id)->exists())
                abort(409,'La ciudad está siendo utilizada por '.$table);
        }
    }

    public function desactivar(Request $request,string $tipo,int $id)
    {
        $this->tabla($tipo);$this->permitir($request,true);
        DB::transaction(function()use($tipo,$id){
            $row=DB::table($tipo)->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404,'Ubicación no encontrada');
            $this->validarDesactivacion($tipo,$id);
            DB::table($tipo)->where('id',$id)->update(['activo'=>0]);
        });
        return response()->json(['id'=>$id,'activo'=>false,'eliminacion'=>'logica']);
    }
}
