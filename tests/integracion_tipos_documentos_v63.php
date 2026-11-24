<?php
declare(strict_types=1);
/** V63: solo MySQL8 efímero en GitHub Actions, NO producción. */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function assertV63(bool $ok,string $message): void
{ if(!$ok)throw new RuntimeException('V63 documentos: '.$message); }
function callV63(string $verb,string $uri,?string $jwt=null,array $body=[]): array
{
 global $kernel;
 $headers=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'];
 if($jwt)$headers['HTTP_AUTHORIZATION']='Bearer '.$jwt;
 $request=Request::create($uri,$verb,[],[],[],$headers,
    in_array($verb,['POST','PATCH','PUT'],true)?json_encode($body,JSON_THROW_ON_ERROR):null);
 $response=$kernel->handle($request);
 $result=[$response->getStatusCode(),json_decode((string)$response->getContent(),true)];
 $kernel->terminate($request,$response);
 return $result;
}
function expectV63(int $expected,array $result,string $step): array
{
 assertV63($result[0]===$expected,$step.' esperaba '.$expected.', obtuvo '.
   $result[0].': '.json_encode($result[1]));
 return $result[1]??[];
}
try {
 assertV63(DB::getDatabaseName()==='rutahyd2_es_local',
   'La prueba está estrictamente prohibida en otra base');
 DB::statement(<<<'SQL'
CREATE TABLE tipo_documentos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(40) NOT NULL,
 codigo_sii SMALLINT UNSIGNED NULL,
 nombre VARCHAR(150) NOT NULL,
 familia VARCHAR(24) NOT NULL,
 documento_venta TINYINT(1) NOT NULL DEFAULT 0,
 documento_compra TINYINT(1) NOT NULL DEFAULT 0,
 disponible_pos TINYINT(1) NOT NULL DEFAULT 0,
 documento_electronico TINYINT(1) NOT NULL DEFAULT 0,
 dias_vencimiento SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_hd_td_codigo (codigo),
 UNIQUE KEY uq_hd_td_sii (codigo_sii),
 UNIQUE KEY uq_hd_td_nombre (nombre),
 CONSTRAINT chk_hd_td_flags CHECK (documento_venta IN (0,1) AND documento_compra IN (0,1)
  AND disponible_pos IN (0,1) AND documento_electronico IN (0,1) AND activo IN (0,1)),
 CONSTRAINT chk_hd_td_familia CHECK (familia IN ('FACTURA','BOLETA','FACTURA_COMPRA',
  'GUIA','NOTA_DEBITO','NOTA_CREDITO','INTERNO')),
 CONSTRAINT chk_hd_td_sii_interno CHECK (
  (familia='INTERNO' AND codigo_sii IS NULL AND documento_electronico=0)
  OR (familia<>'INTERNO' AND codigo_sii IS NOT NULL AND documento_electronico=1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 DB::statement(<<<'SQL'
CREATE TABLE compras (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 documento_id BIGINT UNSIGNED NOT NULL,
 CONSTRAINT fk_test_compra_tipo FOREIGN KEY (documento_id)
 REFERENCES tipo_documentos(id) ON DELETE RESTRICT
) ENGINE=InnoDB
SQL);
 DB::table('tipo_documentos')->insert([
  ['codigo'=>'FACTURA_ELECTRONICA','codigo_sii'=>33,'nombre'=>'Factura electrónica',
   'familia'=>'FACTURA','documento_venta'=>1,'documento_compra'=>1,
   'disponible_pos'=>1,'documento_electronico'=>1,'dias_vencimiento'=>0,'activo'=>1,'orden'=>10],
  ['codigo'=>'ORDEN_COMPRA','codigo_sii'=>null,'nombre'=>'Orden de Compra',
   'familia'=>'INTERNO','documento_venta'=>0,'documento_compra'=>0,
   'disponible_pos'=>0,'documento_electronico'=>0,'dias_vencimiento'=>0,'activo'=>1,'orden'=>90]
 ]);
 $admin=(int)DB::table('roles')->where('codigo','administrador')->value('id');
 assertV63($admin>0,'rol administrador inexistente');
 DB::table('usuarios')->insert([
  'rol_id'=>$admin,'nombre'=>'Admin V63 CI',
  'correo_electronico'=>'doc63_ci@example.invalid',
  'hash_contrasena'=>Hash::make('Solo-CI-V63-Seguro'),'activo'=>1
 ]);
 $jwt=expectV63(200,callV63('POST','/api/v1/sesion',null,[
   'correo_electronico'=>'doc63_ci@example.invalid',
   'contrasena'=>'Solo-CI-V63-Seguro'
 ]),'sesión')['token'];
 expectV63(401,callV63('GET','/api/v1/tipos-documentos'),'JWT requerido');
 $list=expectV63(200,callV63('GET','/api/v1/tipos-documentos',$jwt),'listado');
 assertV63($list['total']===2,'dos documentos del esquema temporal');
 assertV63(count($list['data'])===2,'paginador general');
 $fixture=DB::table('tipo_documentos')->where('codigo','FACTURA_ELECTRONICA')->first();
 $body=[
  'codigo'=>$fixture->codigo,'codigo_sii'=>33,'nombre'=>$fixture->nombre,
  'familia'=>$fixture->familia,'documento_venta'=>1,'documento_compra'=>1,
  'disponible_pos'=>1,'documento_electronico'=>1,
  'dias_vencimiento'=>30,'activo'=>1,'orden'=>10
 ];
 expectV63(200,callV63('PUT','/api/v1/tipos-documentos/'.$fixture->id,
   $jwt,$body),'actualizar días sin cambiar identidad');
 assertV63((int)DB::table('tipo_documentos')->where('id',$fixture->id)
   ->value('dias_vencimiento')===30,'días persistidos');
 $bad=$body;$bad['codigo_sii']=44;
 expectV63(422,callV63('PUT','/api/v1/tipos-documentos/'.$fixture->id,
   $jwt,$bad),'no alterar código SII de documento base');
 $bad=$body;$bad['codigo']='OTRO_CODIGO';
 expectV63(422,callV63('PUT','/api/v1/tipos-documentos/'.$fixture->id,
   $jwt,$bad),'no alterar código interno de documento base');
 $custom=[
  'codigo'=>'TEST_INTERNO','codigo_sii'=>null,'nombre'=>'Documento CI interno',
  'familia'=>'INTERNO','documento_venta'=>0,'documento_compra'=>0,
  'disponible_pos'=>0,'documento_electronico'=>0,
  'dias_vencimiento'=>0,'activo'=>1,'orden'=>100
 ];
 $new=expectV63(201,callV63('POST','/api/v1/tipos-documentos',
   $jwt,$custom),'crear documento interno');
 $id=(int)$new['id'];assertV63($id>0,'id automático');
 expectV63(422,callV63('POST','/api/v1/tipos-documentos',
   $jwt,$custom),'prohibir código duplicado');
 $bad=$custom;$bad['codigo']='TEST_CON_POS';$bad['nombre']='Con POS inválido';
 $bad['disponible_pos']=1;
 expectV63(422,callV63('POST','/api/v1/tipos-documentos',$jwt,$bad),
    'no permitir POS en documentos no venta');
 expectV63(200,callV63('PATCH','/api/v1/tipos-documentos/'.$id.'/estado',
   $jwt,['activo'=>0]),'desactivar doc no utilizado');
 assertV63((int)DB::table('tipo_documentos')->where('id',$id)->value('activo')===0,
   'baja lógica');
 DB::table('compras')->insert(['documento_id'=>$fixture->id]);
 expectV63(422,callV63('PATCH',
   '/api/v1/tipos-documentos/'.$fixture->id.'/estado',$jwt,['activo'=>0]),
   'bloquear baja de documento usado');
 assertV63(DB::table('tipo_documentos')->count()===3,
   'sin cambios a otras tablas ni documentos base');
 echo "V63_DOCUMENTOS_JWT_MYSQL_OK\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
