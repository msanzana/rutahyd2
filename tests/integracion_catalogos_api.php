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
verificar(is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1,
    'El token debe generarse aleatoriamente');

$lectorId = DB::table('usuarios')->insertGetId([
    'rol_id' => $rolLector, 'nombre' => 'Lector sin permisos',
    'correo_electronico' => 'lector@ejemplo.invalid',
    'hash_contrasena' => Hash::make('NoUsarEnProduccion-2026'), 'activo' => 1
]);
$tokenLector = bin2hex(random_bytes(32));
DB::table('tokens_acceso')->insert([
    'usuario_id' => $lectorId, 'hash_token' => hash('sha256', $tokenLector),
    'nombre' => 'ci_lector', 'expira_en' => now()->addHour(),
    'creado_en' => now(),
]);
esperar(403, api('GET', '/api/v1/catalogos/rubros', [], $tokenLector),
    'Permiso productos.gestionar');
esperar(404, api('GET', '/api/v1/catalogos/desconocido', [], $token),
    'Lista blanca de catálogos');

$rubro = crear('rubros', 'Alimentos', null, $token);
$depto = crear('departamentos', 'Abarrotes', $rubro, $token);
$familia = crear('familias', 'Conservas', $depto, $token);
$linea = crear('lineas', 'Vegetales', $familia, $token);
$marca = crear('marcas', 'H&D', null, $token);
$modelo = crear('modelos', 'Tradicional', $marca, $token);
$unidad = crear('tipos_unidad', 'Unidad', null, $token);
$gramaje = crear('gramajes', '500', null, $token);
$envasado = crear('tipos_envasado', 'Lata', null, $token);

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
    'tipoUnidadId' => $unidad, 'gramajeId' => $gramaje,
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

echo "OK: login real, 401/403, CRUD, reserva tributaria, relaciones, producto, legado y cierre de sesión en MySQL efímero.\n";
