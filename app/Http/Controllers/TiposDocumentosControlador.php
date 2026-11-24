<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Administración -> Documentos (catálogo DDL V63).
 * No crea DTE ni modifica el Libro de Compras, OC o inventario.
 */
final class TiposDocumentosControlador extends Controller
{
    private const FAMILIAS=['FACTURA','BOLETA','FACTURA_COMPRA','GUIA',
        'NOTA_DEBITO','NOTA_CREDITO','INTERNO'];
    private const SEMILLAS=['FACTURA_ELECTRONICA','FACTURA_EXENTA',
        'BOLETA_ELECTRONICA','BOLETA_EXENTA','FACTURA_COMPRA_ELECTRONICA',
        'GUIA_DESPACHO_ELECTRONICA','NOTA_DEBITO_ELECTRONICA',
        'NOTA_CREDITO_ELECTRONICA','ORDEN_COMPRA','CONSOLIDACION_RECEPCION'];

    private function json(mixed $data,int $code=200)
    {
        return response()->json($data,$code)->header('Cache-Control','no-store');
    }

    public function listar(Request $request)
    {
        $v=$request->validate([
            'buscar'=>['sometimes','nullable','string','max:120'],
            'familia'=>['sometimes','nullable',Rule::in(self::FAMILIAS)],
            'activo'=>['sometimes','nullable',Rule::in(['0','1',0,1])],
            'page'=>['sometimes','integer','min:1','max:100000'],
        ]);
        $q=DB::table('tipo_documentos');
        if(!empty($v['buscar'])){
            $search='%'.trim((string)$v['buscar']).'%';
            $q->where(fn($b)=>$b->where('nombre','like',$search)
                ->orWhere('codigo','like',$search)
                ->orWhere('codigo_sii','like',$search));
        }
        if(!empty($v['familia']))$q->where('familia',$v['familia']);
        // Los desactivados se conservan pero no se muestran en el listado habitual.
        if(!array_key_exists('activo',$v))$q->where('activo',1);
        if(isset($v['activo']) && $v['activo']!=='')$q->where('activo',(int)$v['activo']);
        return $this->json($q->orderBy('orden')->orderBy('id')
            ->paginate(25,['id','codigo','codigo_sii','nombre','familia',
                'documento_venta','documento_compra','disponible_pos',
                'documento_electronico','dias_vencimiento','activo','orden',
                'creado_en','actualizado_en']));
    }

    public function ver(int $id)
    {
        $documento=DB::table('tipo_documentos')->where('id',$id)->first();
        abort_unless($documento,404,'Documento no encontrado');
        return $this->json($documento);
    }

    private function validar(Request $request,?int $id=null): array
    {
        // No se permite añadir columnas arbitrarias ni manipular el ID,
        // folios, estados o importes por esta API.
        $known=['codigo','codigo_sii','nombre','familia','documento_venta',
            'documento_compra','disponible_pos','documento_electronico',
            'dias_vencimiento','activo','orden'];
        $extra=array_diff(array_keys($request->all()),$known);
        if($extra)throw ValidationException::withMessages([
            'campos'=>['Se recibieron campos que no pertenecen al catálogo de documentos.']
        ]);
        $rules=[
            'codigo'=>['required','string','min:3','max:40',
                'regex:/^[A-Z][A-Z0-9_]*$/',Rule::unique('tipo_documentos','codigo')->ignore($id)],
            'codigo_sii'=>['nullable','integer','min:0','max:999',
                Rule::unique('tipo_documentos','codigo_sii')->ignore($id)],
            'nombre'=>['required','string','min:3','max:150',
                Rule::unique('tipo_documentos','nombre')->ignore($id)],
            'familia'=>['required',Rule::in(self::FAMILIAS)],
            'documento_venta'=>['required','integer','in:0,1'],
            'documento_compra'=>['required','integer','in:0,1'],
            'disponible_pos'=>['required','integer','in:0,1'],
            'documento_electronico'=>['required','integer','in:0,1'],
            'dias_vencimiento'=>['required','integer','min:0','max:65535'],
            'activo'=>['required','integer','in:0,1'],
            'orden'=>['required','integer','min:0','max:65535'],
        ];
        $data=Validator::make($request->all(),$rules)->validate();
        if($data['familia']==='INTERNO'){
            $consolidacion=$data['codigo']==='CONSOLIDACION_RECEPCION';
            $codigoValido=$consolidacion ? ($data['codigo_sii']!==null && (int)$data['codigo_sii']===0) : $data['codigo_sii']===null;
            if(!$codigoValido||(int)$data['documento_electronico']!==0)
                throw ValidationException::withMessages([
                    'codigo_sii'=>['Los documentos internos no tienen código SII ni son DTE.']
                ]);
        }elseif($data['codigo_sii']===null||(int)$data['codigo_sii']<1||(int)$data['documento_electronico']!==1){
            throw ValidationException::withMessages([
                'codigo_sii'=>['Los documentos tributarios deben indicar código SII y ser electrónicos.']
            ]);
        }
        if((int)$data['disponible_pos']===1 && (int)$data['documento_venta']!==1)
            throw ValidationException::withMessages([
                'disponible_pos'=>['Solo los documentos habilitados para venta pueden estar disponibles en POS.']
            ]);
        return $data;
    }

    public function crear(Request $request)
    {
        $data=$this->validar($request);
        abort_if(in_array($data['codigo'],self::SEMILLAS,true),422,
            'Los documentos base se instalan mediante el DDL oficial.');
        $id=DB::table('tipo_documentos')->insertGetId($data);
        return $this->json(['id'=>$id,'mensaje'=>'Documento creado',
            'documento'=>DB::table('tipo_documentos')->where('id',$id)->first()],201);
    }

    public function actualizar(Request $request,int $id)
    {
        $original=DB::table('tipo_documentos')->where('id',$id)->first();
        abort_unless($original,404,'Documento no encontrado');
        $data=$this->validar($request,$id);
        // Identidad legal y clasificación SII de los 9 documentos oficiales
        // quedan protegidas. Se permiten los atributos operativos.
        if(in_array($original->codigo,self::SEMILLAS,true)){
            foreach(['codigo','codigo_sii','familia','documento_electronico',
                    'documento_venta','documento_compra'] as $field){
                if((string)($original->{$field}??'')!==(string)($data[$field]??''))
                    throw ValidationException::withMessages([
                        $field=>['Los datos normativos del documento oficial son de solo lectura.']
                    ]);
            }
        }
        $data['actualizado_en']=now();
        DB::table('tipo_documentos')->where('id',$id)->update($data);
        return $this->json(['id'=>$id,'mensaje'=>'Documento actualizado',
            'documento'=>DB::table('tipo_documentos')->where('id',$id)->first()]);
    }

    public function cambiarEstado(Request $request,int $id)
    {
        $doc=DB::table('tipo_documentos')->where('id',$id)->first();
        abort_unless($doc,404,'Documento no encontrado');
        $v=$request->validate(['activo'=>['required','integer','in:0,1']]);
        $wanted=(int)$v['activo'];
        if((int)$doc->activo===$wanted)return $this->json(['id'=>$id,'activo'=>$wanted]);
        // Nunca permitir desactivar un documento actualmente utilizado.
        if($wanted===0){
            foreach(['compras'=>'documento_id'] as $tabla=>$campo){
                if(DB::table($tabla)->where($campo,$id)->exists())
                    throw ValidationException::withMessages([
                        'activo'=>['No se puede desactivar un documento asociado a compras.']
                    ]);
            }
        }
        DB::table('tipo_documentos')->where('id',$id)
            ->update(['activo'=>$wanted,'actualizado_en'=>now()]);
        return $this->json(['id'=>$id,'activo'=>$wanted,'mensaje'=>'Estado actualizado']);
    }
}
