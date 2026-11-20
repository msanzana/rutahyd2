<?php
declare(strict_types=1);

/**
 * Uso local exclusivamente: php tools/generar_admin_sql.php
 * Genera INSERT con hash Argon2id/bcrypt sin guardar contraseña en archivos.
 * NO conecta a InfinityFree ni ejecuta SQL automáticamente.
 * Requiere una consola interactiva que pueda ocultar la contraseña.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo se admite ejecución local desde terminal.\n");
    exit(1);
}
if (!function_exists('password_hash') || !is_file('/dev/tty')) {
    fwrite(STDERR, "Se necesita PHP CLI y terminal TTY para leer la contraseña en secreto.\n");
    exit(1);
}
$tty = fopen('/dev/tty','r+');
if (!$tty) exit(1);
fwrite($tty,"Correo electrónico del nuevo administrador: ");
$email = trim((string)fgets($tty));
fwrite($tty,"Nombre a mostrar: ");
$nombre = trim((string)fgets($tty));
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190
    || mb_strlen($nombre)<3 || mb_strlen($nombre)>160) {
    fwrite(STDERR,"Correo o nombre no válidos.\n");
    exit(1);
}
fwrite($tty,"Contraseña nueva (no aparecerá en pantalla): ");
$stty = (string)@shell_exec('stty -g </dev/tty 2>/dev/null');
if (trim($stty)==='') {
    fwrite(STDERR,"\nNo se puede desactivar el eco del teclado de forma segura.\n");
    exit(1);
}
@shell_exec('stty -echo </dev/tty');
try {
    $contrasena = rtrim((string)fgets($tty),"\r\n");
} finally {
    @shell_exec('stty '.escapeshellarg(trim($stty)).' </dev/tty');
    fwrite($tty,"\n");
}
if (strlen($contrasena)<12) {
    fwrite(STDERR,"La contraseña debe tener al menos 12 caracteres.\n");
    exit(1);
}
$algoritmo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$hash = password_hash($contrasena, $algoritmo);
unset($contrasena);
if (!$hash) throw new RuntimeException('No se pudo generar el hash.');
$quoted = static fn(string $s): string => "'".str_replace("'","''",$s)."'";
echo "\n-- INSERT de administración: importar SOLO en la base española ya respaldada.\n";
echo "-- La salida contiene un verificador de contraseña: tratarla como secreta.\n";
echo "INSERT INTO usuarios (rol_id,nombre,correo_electronico,hash_contrasena,activo,creado_en,actualizado_en)\n";
echo "SELECT id, ".$quoted($nombre).", ".$quoted($email).", ".$quoted($hash).", 1, NOW(), NOW()\n";
echo "FROM roles WHERE codigo='administrador'\n";
echo "AND NOT EXISTS (SELECT 1 FROM usuarios WHERE correo_electronico=".$quoted($email).") LIMIT 1;\n";
