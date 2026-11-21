#!/usr/bin/env python3
"""Prepara sonda efímera de alta de administrador. No revela PII ni credenciales."""
from __future__ import annotations

import base64
import json
import os
import pathlib
import re
import secrets
import sys
import time
from cryptography.hazmat.primitives import serialization, hashes
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives.kdf.hkdf import HKDF
from cryptography.hazmat.primitives.ciphers.aead import AESGCM


def b64(s: str) -> str:
    return base64.b64encode(s.encode("utf-8")).decode("ascii")


def load_env(p: pathlib.Path) -> dict[str, str]:
    data = {}
    for line in p.read_text(encoding="utf-8").splitlines():
        if not line.strip() or line.lstrip().startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        try:
            v = json.loads(v)
        except ValueError:
            v = v.strip().strip("\"'")
        data[k.strip()] = str(v)
    return data


def check_rut(rut: str) -> bool:
    clean = re.sub(r"[.\s-]", "", rut).upper()
    if not re.fullmatch(r"\d{7,8}[\dK]", clean):
        return False
    total = sum(int(d) * (i % 6 + 2) for i, d in enumerate(reversed(clean[:-1])))
    dv = 11 - total % 11
    return clean[-1] == ("0" if dv == 11 else "K" if dv == 10 else str(dv))


def main() -> None:
    if len(sys.argv) != 5:
        raise SystemExit("Uso: script env_privado privada_envuelta payload_cifrado sonda_php")
    env_path, wrapped_path, payload_path, destination = map(pathlib.Path, sys.argv[1:])
    env = load_env(env_path)
    if env.get("APP_ENV") != "production" or env.get("APP_DEBUG") != "false":
        raise SystemExit("Entorno no es de producción protegida")
    if env.get("DB_DATABASE") != "if0_43460400_rutahyd2_es":
        raise SystemExit("Destino de DB no coincide")
    jwt = env.get("RUTAHD_JWT_SECRET", "")
    if len(jwt) < 64:
        raise SystemExit("Secreto JWT insuficiente")
    wrapped = json.loads(wrapped_path.read_text())
    salt = base64.b64decode(wrapped["salt"])
    nonce = base64.b64decode(wrapped["nonce"])
    encrypted_priv = base64.b64decode(wrapped["ciphertext"])
    derived = HKDF(
        algorithm=hashes.SHA256(),
        length=32,
        salt=salt,
        info=b"rutahd2-private-admin-bootstrap-20261121-v1",
    ).derive(jwt.encode("utf-8"))
    private_pem = AESGCM(derived).decrypt(nonce, encrypted_priv, b"rutahd2-bootstrap-private")
    private = serialization.load_pem_private_key(private_pem, password=None)
    encrypted_payload = json.loads(payload_path.read_text())
    raw = private.decrypt(
        base64.b64decode(encrypted_payload["ciphertext_b64"]),
        padding.OAEP(mgf=padding.MGF1(algorithm=hashes.SHA256()), algorithm=hashes.SHA256(), label=None),
    )
    payload = json.loads(raw)
    nombre = str(payload["nombre"]).strip()
    rut = str(payload["rut"]).strip()
    correo = str(payload["correo_electronico"]).strip().lower()
    if not 3 <= len(nombre) <= 160 or not check_rut(rut) or not re.fullmatch(r"[^@\s]{1,100}@[^@\s]{1,150}", correo):
        raise SystemExit("Datos del administrador no pasaron la validación")

    name = "rutahd2_admin_" + secrets.token_hex(16) + ".php"
    token = secrets.token_hex(32)
    expires = int(time.time()) + 900
    php = r'''<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST' ||
   time()>__EXPIRES__ ||
   !hash_equals('__TOKEN__',(string)($_SERVER['HTTP_X_RUTAHD_BOOTSTRAP']??''))) {
   http_response_code(404);exit;
}
try {
  $body=file_get_contents('php://input');
  if(!is_string($body)||strlen($body)>512)throw new RuntimeException('body');
  $request=json_decode($body,true,8,JSON_THROW_ON_ERROR);
  $password=(string)($request['contrasena']??'');
  if(!preg_match('/^[A-Za-z0-9_-]{24}$/D',$password))throw new RuntimeException('password_format');

  $nombre=base64_decode('__NOMBRE_B64__',true);
  $rut=base64_decode('__RUT_B64__',true);
  $correo=base64_decode('__CORREO_B64__',true);
  if(!$nombre||!$rut||!$correo)throw new RuntimeException('payload');
  $root=__DIR__.'/api-v2';
  require_once $root.'/vendor/autoload.php';
  \Dotenv\Dotenv::createImmutable($root)->safeLoad();
  if(($_ENV['APP_ENV']??'')!=='production' ||
     ($_ENV['DB_HOST']??'')!=='sql307.infinityfree.com' ||
     ($_ENV['DB_DATABASE']??'')!=='if0_43460400_rutahyd2_es')throw new RuntimeException('database');

  $dsn='mysql:host='.$_ENV['DB_HOST'].';port=3306;dbname='.$_ENV['DB_DATABASE'].';charset=utf8mb4';
  $pdo=new \PDO($dsn,(string)$_ENV['DB_USERNAME'],(string)$_ENV['DB_PASSWORD'],[
    \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_TIMEOUT=>7
  ]);
  $existing=(int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
  if($existing!==0)throw new RuntimeException('existing_users');
  $role=$pdo->query("SELECT id FROM roles WHERE codigo='administrador' LIMIT 1")->fetchColumn();
  if(!$role)throw new RuntimeException('missing_admin_role');
  $tables=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
  if($tables!==45 && $tables!==46)throw new RuntimeException('unexpected_schema');

  $hasReset=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='restablecimientos_contrasena'")->fetchColumn();
  $ddl18=false;
  if(!$hasReset){
    $migration=file_get_contents($root.'/database/espanol/18_recuperacion_contrasenas.sql');
    if(!$migration||!str_contains($migration,'CREATE TABLE IF NOT EXISTS restablecimientos_contrasena'))
       throw new RuntimeException('ddl18_missing');
    $pdo->exec($migration);
    $ddl18=true;
  }

  $hasRut=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='rut'")->fetchColumn();
  $ddl19=false;
  if(!$hasRut){
    $migration=file_get_contents($root.'/database/espanol/19_rut_usuarios.sql');
    if(!$migration||!str_contains($migration,'ADD UNIQUE KEY uq_usuarios_rut'))
       throw new RuntimeException('ddl19_missing');
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN rut VARCHAR(12) NULL AFTER nombre, ADD UNIQUE KEY uq_usuarios_rut (rut)');
    $ddl19=true;
  }
  $columns=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='rut'")->fetchColumn();
  $reset=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='restablecimientos_contrasena'")->fetchColumn();
  if($columns!==1||$reset!==1)throw new RuntimeException('ddl_verification');
  if(!defined('PASSWORD_ARGON2ID'))throw new RuntimeException('argon2id');
  $hash=password_hash($password,PASSWORD_ARGON2ID,['memory_cost'=>65536,'time_cost'=>3,'threads'=>1]);
  if(!$hash)throw new RuntimeException('hash');

  $pdo->beginTransaction();
  try {
    $insert=$pdo->prepare('INSERT INTO usuarios (rol_id,nombre,rut,correo_electronico,hash_contrasena,activo) VALUES (?,?,?,?,?,1)');
    $insert->execute([(int)$role,$nombre,$rut,$correo,$hash]);
    $id=(int)$pdo->lastInsertId();
    $check=$pdo->prepare('SELECT hash_contrasena FROM usuarios WHERE id=? AND rol_id=?');
    $check->execute([$id,(int)$role]);
    $actual=$check->fetchColumn();
    if($id<1||!is_string($actual)||!password_verify($password,$actual)){
      throw new RuntimeException('account_verify');
    }
    $pdo->commit();
  }catch(\Throwable $e){$pdo->rollBack();throw $e;}

  $jwtOk=false;
  try{
    $app=require $root.'/bootstrap/app.php';
    $loginRequest=\Illuminate\Http\Request::create('/api/v1/sesion','POST',[
      'correo_electronico'=>$correo,'contrasena'=>$password
    ],[],[],['HTTP_ACCEPT'=>'application/json','HTTP_HOST'=>'rutahyd2.free.nf','HTTPS'=>'on']);
    $kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
    $loginResponse=$kernel->handle($loginRequest);
    $responseData=json_decode((string)$loginResponse->getContent(),true);
    $jwtOk=$loginResponse->getStatusCode()===200 &&
           is_array($responseData) &&
           ($responseData['token_type']??null)==='Bearer' &&
           isset($responseData['token']) &&
           ($responseData['usuario']['rol']??null)==='administrador';
    $kernel->terminate($loginRequest,$loginResponse);
  }catch(\Throwable $e){$jwtOk=false;}
  $after=(int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
  $tableAfter=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
  echo json_encode([
    'ok'=>$after===1 && $tableAfter===46,
    'usuario_creado'=>$after===1,
    'usuario_id'=>$id,
    'tablas'=>$tableAfter,
    'ddl18_aplicado'=>$ddl18,
    'ddl19_aplicado'=>$ddl19,
    'jwt_privado'=>$jwtOk
  ],JSON_UNESCAPED_UNICODE);
}catch(\Throwable $e){
  http_response_code(503);
  echo json_encode(['ok'=>false,'clase_error'=>get_class($e)],JSON_UNESCAPED_UNICODE);
}
'''
    php = (
        php.replace("__EXPIRES__", str(expires))
        .replace("__TOKEN__", token)
        .replace("__NOMBRE_B64__", b64(nombre))
        .replace("__RUT_B64__", b64(rut))
        .replace("__CORREO_B64__", b64(correo))
    )
    destination.write_text(php, encoding="utf-8")
    destination.chmod(0o600)
    with open(os.environ["GITHUB_ENV"], "a", encoding="utf-8") as env_file:
        env_file.write(f"PROBE_NAME={name}\nPROBE_TOKEN={token}\n")
    print("Sonda de alta protegida preparada: datos descifrados únicamente en el runner, no registrados.")


if __name__ == "__main__":
    main()
