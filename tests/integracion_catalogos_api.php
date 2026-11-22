<?php
declare(strict_types=1);

/**
 * Prueba aislada de API real con MySQL temporal en GitHub Actions.
 * NUNCA ejecutar en InfinityFree ni sobre datos productivos.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$httpKernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function verificar(bool $condicion, string $mensaje): void
{
    if (!$condicion) throw new RuntimeException('Fallo prueba: ' . $mensaje);
}

function api(string $metodo, string $ruta, array $datos = [], ?string $token = null): array
{
    global $httpKernel;
    $headers = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];
    if ($token !== null) $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    $solicitud = Request::create($ruta, $metodo, [], [], [], $headers,
        in_array($metodo, ['POST', 'PUT', 'PATCH'], true)
            ? json_encode($datos, JSON_THROW_ON_ERROR) : null);
    $respuesta = $httpKernel->handle($solicitud);
    $codigo = $respuesta->getStatusCode();
    $cuerpo = json_decode((string) $respuesta->getContent(), true);
    $httpKernel->terminate($solicitud, $respuesta);
    return [$codigo, $cuerpo];
}

function esperar(int $esperado, array $resultado, string $accion): array
{
    verificar($resultado[0] === $esperado,
        $accion . ' esperaba HTTP ' . $esperado . ', obtuvo ' . $resultado[0]);
    return is_array($resultado[1]) ? $resultado[1] : [];
}

function crear(string $catalogo, string $nombre, ?int $padre, string $token): int
{
    $respuesta = esperar(201, api('POST', '/api/v1/catalogos/' . $catalogo,
        ['nombre' => $nombre, 'activo' => true, 'parentId' => $padre], $token),
        'Crear ' . $catalogo);
    verificar(isset($respuesta['id']) && (int) $respuesta['id'] > 0,
        'El catálogo ' . $catalogo . ' debe retornar ID numérico');
    return (int) $respuesta['id'];
}

try {
// La base de este test debe ser efímera y exclusivamente local a CI.
verificar(DB::getDatabaseName() === 'rutahyd2_es_local',
    'La prueba solo está autorizada para rutahyd2_es_local');

$rolAdmin = DB::table('roles')->where('codigo', 'administrador')->value('id');
$rolLector = DB::table('roles')->where('codigo', 'supervisor')->value('id');
verificar($rolAdmin !== null && $rolLector !== null, 'Faltan semillas españolas');
$clave = 'ClaveCI!soloParaPruebas-2026';
$usuarioId = DB::table('usuarios')->insertGetId([
    'rol_id' => $rolAdmin, 'nombre' => 'Administrador CI',
    'correo_electronico' => 'integracion@ejemplo.invalid',
    'hash_contrasena' => Hash::make($clave), 'activo' => 1
]);
verificar($usuarioId > 0, 'Administrador temporal no creado');

esperar(401, api('GET', '/api/v1/catalogos/rubros'), 'Rechazo anónimo');
$sesion = esperar(200, api('POST', '/api/v1/sesion',
    ['correo_electronico' => 'integracion@ejemplo.invalid', 'contrasena' => $clave]),
    'Inicio de sesión de prueba');
$token = $sesion['token'] ?? null;
verificar(is_string($token) &&
    preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $token) === 1,
    'El servidor debe retornar un JWT Bearer de tres segmentos');
verificar(($sesion['token_type'] ?? null) === 'Bearer', 'Tipo de token Bearer');
$partes=explode('.', $token);
$encabezado=json_decode(base64_decode(strtr($partes[0], '-_', '+/')),true);
verificar(($encabezado['alg'] ?? null)==='HS256','Algoritmo JWT HS256 obligatorio');
esperar(401,api('GET','/api/v1/catalogos/rubros',[],'a'.str_repeat('f',63)),
    'Tokens heredados no sirven como JWT');
$falso=$partes;
$falso[2] = $falso[2][0] === 'A' ? 'B'.substr($falso[2],1) : 'A'.substr($falso[2],1);
esperar(401,api('GET','/api/v1/catalogos/rubros',[],implode('.',$falso)),
    'Firma JWT manipulada rechazada');
$pasado=\Firebase\JWT\JWT::encode([
    'iss'=>'rutahd2-api','aud'=>'rutahd2-web','sub'=>(string)$usuarioId,
    'iat'=>time()-7200,'nbf'=>time()-7200,'exp'=>time()-3600,
    'jti'=>bin2hex(random_bytes(32)),
],(string)config('rutahd.jwt_secret'),'HS256');
esperar(401,api('GET','/api/v1/catalogos/rubros',[],$pasado),
    'JWT expirado rechazado');

$lectorId = DB::table('usuarios')->insertGetId([
    'rol_id' => $rolLector, 'nombre' => 'Lector sin permisos',
    'correo_electronico' => 'lector@ejemplo.invalid',
    'hash_contrasena' => Hash::make('NoUsarEnProduccion-2026'), 'activo' => 1
]);
$sesionLector=esperar(200,api('POST','/api/v1/sesion',[
    'correo_electronico'=>'lector@ejemplo.invalid',
    'contrasena'=>'NoUsarEnProduccion-2026',
]),'Login JWT de perfil lector');
$tokenLector=$sesionLector['token'];
esperar(403, api('GET', '/api/v1/catalogos/rubros', [], $tokenLector),
    'Permiso separado rubros.ver');
esperar(403, api('GET', '/api/v1/perfiles/catalogos', [], $tokenLector),
    'Solo admin puede configurar perfiles');

$perfiles = esperar(200, api('GET', '/api/v1/perfiles/catalogos', [], $token),
    'Leer perfiles');
verificar(count($perfiles['catalogos']) === 10, 'Deben existir diez submódulos');
$modulos = [];
foreach (array_keys($perfiles['catalogos']) as $key) {
    $modulos[$key] = ['ver'=>false, 'gestionar'=>false];
}
$mal = $modulos;
$mal['rubros'] = ['ver'=>false,'gestionar'=>true];
esperar(422, api('PUT', '/api/v1/perfiles/' . $rolLector . '/catalogos',
    ['modulos'=>$mal], $token), 'Gestión implica visibilidad');

$modulos['rubros']['ver'] = true;
esperar(200, api('PUT', '/api/v1/perfiles/' . $rolLector . '/catalogos',
    ['modulos'=>$modulos], $token), 'Conceder ver solo Rubros');
esperar(200, api('GET', '/api/v1/catalogos/rubros', [], $tokenLector),
    'Rol supervisor puede ver rubros');
esperar(403, api('POST', '/api/v1/catalogos/rubros',
    ['nombre'=>'No escribir','activo'=>true,'parentId'=>null], $tokenLector),
    'Rol supervisor no puede crear rubros');
esperar(403, api('GET', '/api/v1/catalogos/marcas', [], $tokenLector),
    'Otros catálogos siguen ocultos');

$modulos['rubros']['gestionar'] = true;
esperar(200, api('PUT', '/api/v1/perfiles/' . $rolLector . '/catalogos',
    ['modulos'=>$modulos], $token), 'Conceder gestionar Rubros');
$otroRubro = crear('rubros','Catalogo autorizado',null,$tokenLector);
verificar($otroRubro > 0,'Rol autorizado puede crear rubro');
$modulos['rubros'] = ['ver'=>false,'gestionar'=>false];
esperar(200, api('PUT', '/api/v1/perfiles/' . $rolLector . '/catalogos',
    ['modulos'=>$modulos], $token), 'Revocar permisos de Rubros');
esperar(403, api('GET', '/api/v1/catalogos/rubros', [], $tokenLector),
    'Revocación efectiva sin nuevo login');
esperar(403, api('PUT', '/api/v1/perfiles/' . $rolAdmin . '/catalogos',
    ['modulos'=>$modulos], $token), 'No alterar rol admin');

$usuariosPerfil=esperar(200,api('GET','/api/v1/perfiles/usuarios',[],$token),
    'Listar usuarios por perfil');
verificar(count($usuariosPerfil['usuarios'])>=2,'Debe mostrar usuarios existentes');
esperar(403,api('GET','/api/v1/perfiles/usuarios',[],$tokenLector),
    'Un usuario común no ve otros perfiles');
$rolVendedor=(int)DB::table('roles')->where('codigo','vendedor')->value('id');
esperar(200,api('PUT','/api/v1/perfiles/usuarios/'.$lectorId.'/rol',
    ['rol_id'=>$rolVendedor],$token),'Asignación de perfil a un usuario');
verificar((int)DB::table('usuarios')->where('id',$lectorId)->value('rol_id')===$rolVendedor,
    'Cambio de rol persistido en CI');
esperar(403,api('PUT','/api/v1/perfiles/usuarios/'.$usuarioId.'/rol',
    ['rol_id'=>$rolVendedor],$token),'No degradar propia cuenta admin');
esperar(403,api('PUT','/api/v1/perfiles/usuarios/'.$lectorId.'/rol',
    ['rol_id'=>$rolAdmin],$token),'No elevar a administrador por esta pantalla');
esperar(404, api('GET', '/api/v1/catalogos/desconocido', [], $token),
    'Lista blanca de catálogos');

$rubro = crear('rubros', 'Alimentos', null, $token);
$depto = crear('departamentos', 'Abarrotes', $rubro, $token);
$familia = crear('familias', 'Conservas', $depto, $token);
$linea = crear('lineas', 'Vegetales', $familia, $token);
$marca = crear('marcas', 'H&D', null, $token);
$modelo = crear('modelos', 'Tradicional', $marca, $token);
$unidad = (int)DB::table('tipos_unidad')->where('nombre','Unidad')->value('id');
$tipo = crear('tipos_codigos_barras', 'TIPO ESPECIAL CI', null, $token);
$envasado = (int)DB::table('tipos_envasado')->where('nombre','Lata')->value('id');

esperar(409, api('POST', '/api/v1/catalogos/rubros',
    ['nombre' => 'Alimentos', 'activo' => true, 'parentId' => null], $token),
    'Bloqueo duplicados');
esperar(409, api('DELETE', '/api/v1/catalogos/rubros/' . $rubro, [], $token),
    'Proteger catálogo con hijos');

$tributarias = esperar(200, api('GET',
    '/api/v1/catalogos/condiciones_tributarias', [], $token), 'Listar tributarias');
$exento = null;
foreach ($tributarias['items'] ?? [] as $item) {
    if ($item['nombre'] === 'Exento') $exento = (int) $item['id'];
}
verificar($exento !== null, 'Exento debe existir');
esperar(409, api('DELETE',
    '/api/v1/catalogos/condiciones_tributarias/' . $exento, [], $token),
    'Exento reservado no debe borrarse');
esperar(422, api('PUT',
    '/api/v1/catalogos/condiciones_tributarias/' . $exento,
    ['nombre' => 'Exento', 'activo' => false, 'parentId' => null], $token),
    'Exento reservado no debe desactivarse');

$categoriaAnterior = DB::table('categorias_productos')->insertGetId([
    'nombre' => 'Categoría histórica'
]);
$producto = DB::table('productos')->insertGetId([
    'categoria_id' => $categoriaAnterior, 'sku' => 'SKU-PRUEBA-V2',
    'nombre' => 'Producto CI de prueba', 'activo' => 1
]);

$actual = esperar(200, api('GET',
    '/api/v1/productos/' . $producto . '/clasificacion', [], $token),
    'Consultar producto previo');
verificar($actual['sku'] === 'SKU-PRUEBA-V2' && $actual['nombre'] === 'Producto CI de prueba',
    'Consultar identidad SKU');
verificar($actual['clasificacion']['lineaId'] === null, 'Legacy sin línea inicial');

$datos = [
    'rubroId' => $rubro, 'departamentoId' => $depto,
    'familiaId' => $familia, 'lineaId' => $linea,
    'marcaId' => $marca, 'modeloId' => $modelo,
    'tipoUnidadId' => $unidad,
    'tipoEnvasadoId' => $envasado, 'condicionTributariaId' => $exento
];
$incorrectos = $datos;
$incorrectos['departamentoId'] = 999999;
esperar(422, api('PATCH', '/api/v1/productos/' . $producto . '/clasificacion',
    $incorrectos, $token), 'Rechazo de clasificación inconsistente');
esperar(200, api('PATCH', '/api/v1/productos/' . $producto . '/clasificacion',
    $datos, $token), 'Clasificación válida');
$despues = esperar(200, api('GET',
    '/api/v1/productos/' . $producto . '/clasificacion', [], $token),
    'Leer clasificación guardada');
verificar((int) $despues['clasificacion']['lineaId'] === $linea,
    'La línea debe quedar guardada');
verificar((int) DB::table('productos')->where('id', $producto)->value('categoria_id')
    === $categoriaAnterior, 'La categoría histórica no debe modificarse');
esperar(409, api('DELETE', '/api/v1/catalogos/marcas/' . $marca, [], $token),
    'No eliminar marca referenciada');
esperar(204, api('DELETE', '/api/v1/sesion', [], $token),
    'Cerrar sesión de prueba');
esperar(401, api('GET', '/api/v1/catalogos/rubros', [], $token),
    'Token revocado');

echo "OK: login real, 401/403, matriz de perfiles, permisos por módulo, CRUD, taxonomía y legado en MySQL efímero.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR INTEGRACIÓN: " . $e->getMessage() . "\n");
    exit(1);
}
