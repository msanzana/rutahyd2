# Ruta H&D V2: genera alta de administrador sin guardar ni enviar contraseña.
# Ejecutar en Windows PowerShell: .\tools\crear_admin_sql_windows.ps1
# Requiere PHP CLI con soporte PASSWORD_ARGON2ID.
param([string]$PhpExe = "php")
$ErrorActionPreference = "Stop"
$command = Get-Command $PhpExe -ErrorAction SilentlyContinue
if (-not $command) { throw "PHP CLI no está disponible. Instala PHP o indica -PhpExe con su ruta." }

$email = (Read-Host "Correo del administrador").Trim()
$nombre = (Read-Host "Nombre para mostrar").Trim()
if ($email -notmatch "^[^\s@]+@[^\s@]+\.[^\s@]+$" -or $email.Length -gt 190) {
  throw "Correo electrónico inválido."
}
if ($nombre.Length -lt 3 -or $nombre.Length -gt 160) {
  throw "Nombre inválido."
}
$segura = Read-Host "Contraseña NUEVA (no se muestra)" -AsSecureString
$puntero = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
$texto = $null
try {
  $texto = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($puntero)
  if ($texto.Length -lt 12) { throw "Usa una contraseña nueva de al menos 12 caracteres." }

  $codigo = '$p=stream_get_contents(STDIN);if(!defined("PASSWORD_ARGON2ID")){fwrite(STDERR,"Argon2id no disponible");exit(4);}echo password_hash($p,PASSWORD_ARGON2ID);'
  $info = New-Object System.Diagnostics.ProcessStartInfo
  $info.FileName = $command.Source
  $info.Arguments = ('-r "' + $codigo.Replace('"','\"') + '"')
  $info.UseShellExecute = $false
  $info.RedirectStandardInput = $true
  $info.RedirectStandardOutput = $true
  $info.RedirectStandardError = $true
  $info.CreateNoWindow = $true
  $proceso = New-Object System.Diagnostics.Process
  $proceso.StartInfo = $info
  if (-not $proceso.Start()) { throw "No se pudo iniciar PHP CLI." }
  $proceso.StandardInput.Write($texto)
  $proceso.StandardInput.Close()
  $hash = $proceso.StandardOutput.ReadToEnd().Trim()
  $errorPhp = $proceso.StandardError.ReadToEnd()
  $proceso.WaitForExit()
  if ($proceso.ExitCode -ne 0 -or -not $hash.StartsWith('$argon2id$')) {
    throw "PHP no generó un hash Argon2id válido. $errorPhp"
  }
  # Solo el verificador (hash) se incorpora a SQL; no la contraseña.
  $escapar = { param($valor) $valor.Replace("'","''") }
  $emailSql = & $escapar $email
  $nombreSql = & $escapar $nombre
  $hashSql = & $escapar $hash
  $sql = @"
INSERT INTO usuarios (rol_id,nombre,correo_electronico,hash_contrasena,activo,creado_en,actualizado_en)
SELECT id, '$nombreSql', '$emailSql', '$hashSql', 1, NOW(), NOW()
FROM roles WHERE codigo='administrador'
AND NOT EXISTS (SELECT 1 FROM usuarios WHERE correo_electronico='$emailSql') LIMIT 1;
"@
  Set-Clipboard -Value $sql
  Write-Host "SQL con hash Argon2id copiado al portapapeles." -ForegroundColor Green
  Write-Host "Importa SOLO en if0_43460400_rutahyd2_es (phpMyAdmin), verifica la cuenta y limpia el portapapeles."
  Write-Host "No compartas este SQL ni su hash en ChatGPT, GitHub, correos ni capturas."
}
finally {
  if ($puntero -ne [IntPtr]::Zero) {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($puntero)
  }
  $texto = $null
  $segura = $null
}
