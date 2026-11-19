<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$host=getenv('DB_HOST')?:'127.0.0.1';
$db=getenv('DB_DATABASE')?:'rutahyd2_es_local';
$user=getenv('DB_USERNAME')?:'root';
$pass=getenv('DB_PASSWORD')?:'';
$pdo=new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4",$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
]);
$source=file_get_contents(dirname(__DIR__).'/database/espanol/01_esquema_completo_34_tablas_es.sql');
preg_match_all('/^CREATE TABLE IF NOT EXISTS (\w+)\s*\(/m',$source,$m);
$tablas=$m[1];
if(count($tablas)!==34)throw new RuntimeException('DDL no contiene las 34 tablas.');
$existentes=$pdo->query("SELECT table_name FROM information_schema.tables
 WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
foreach($tablas as $tabla)if(!in_array($tabla,$existentes,true))throw new RuntimeException("Falta {$tabla}");
foreach(['tokens_acceso','migraciones'] as $tabla){
    if(!in_array($tabla,$existentes,true))throw new RuntimeException("Falta tabla técnica {$tabla}");
}
$archivos=glob(dirname(__DIR__).'/app/Models/*.php');
if(count($archivos)!==34)throw new RuntimeException('Se esperaban 34 modelos Eloquent');
$vistos=[];
foreach($archivos as $path){
    $clase='App\\Models\\'.basename($path,'.php');
    if(!class_exists($clase))throw new RuntimeException("Modelo PHP no encontrado: {$clase}");
    $modelo=new $clase();
    $nombre=$modelo->getTable();
    if(!in_array($nombre,$tablas,true))throw new RuntimeException("Modelo usa tabla desconocida: {$nombre}");
    $vistos[]=$nombre;
    $reales=$pdo->query("SHOW COLUMNS FROM `{$nombre}`")->fetchAll(PDO::FETCH_COLUMN);
    foreach($modelo->getFillable() as $campo){
        if(!in_array($campo,$reales,true))throw new RuntimeException("Campo inexistente en {$nombre}: {$campo}");
    }
}
if(count(array_unique($vistos))!==34)throw new RuntimeException('Tabla repetida en modelos');
$sql="SELECT referenced_table_name FROM information_schema.key_column_usage
 WHERE table_schema=DATABASE() AND table_name='detalles_ventas' AND column_name='producto_id'
 AND referenced_table_name IS NOT NULL";
if($pdo->query($sql)->fetchColumn()!=='productos')throw new RuntimeException('FK detalle-producto incorrecta');
$roles=(int)$pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
$bodegas=(int)$pdo->query('SELECT COUNT(*) FROM bodegas')->fetchColumn();
$permisos=(int)$pdo->query('SELECT COUNT(*) FROM permisos')->fetchColumn();
if($roles!==5||$bodegas!==2||$permisos!==12)throw new RuntimeException('Semillas inesperadas');
echo "OK: 34 tablas, 34 modelos, 2 tablas técnicas, FK producto y 19 semillas comprobadas.\n";
