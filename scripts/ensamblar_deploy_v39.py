#!/usr/bin/env python3
"""Ruta H&D V39: construir nueva versión SOLO desde archivos FTPS descargados.

Confirma la identidad de cada módulo productivo con V38 + hotfix de copyprod,
integra parches V39 en Laravel con 3-way merge y preserva index.html productivo.
No ejecuta escrituras MySQL, no hace conexiones FTP ni publica.
"""
from __future__ import annotations
import pathlib, subprocess, re, runpy
ROOT=pathlib.Path('/tmp/v39')
BACK=pathlib.Path('/tmp/v39back')
FRONT=pathlib.Path('/tmp/v39front')
BACK_BASE='cf61e34aa195cc41fab9513f1959dd7d51e3246c'
FRONT_BASE='a36bc7b81d653061ed3b2646091d31a2d5c9fbbc'
FRONT_COPY='978068533a66153ec2eacdacf1c8293ef8f62177'
BACK_EXISTING=[
 'routes/api.php','app/Http/Controllers/EntidadesComercialesControlador.php',
 'app/Models/Proveedor.php'
]
BACK_NEW=[
 'app/Http/Controllers/GirosSiiControlador.php',
 'app/Http/Controllers/BuscadorProductosControlador.php'
]
FRONT_COPY_ONLY=[
 'admin-remoto.js','app-produccion.js','galeria-productos.js',
 'listas-precios.js','login-produccion.js','menu-usuario.js',
 'perfiles.js','productos-remotos.js','recuperacion.js','usuarios.js'
]
FRONT_SURGICAL=['modulos-oficiales.js','proveedores-productos.js','ventas-precios.js']
FRONT_OTHER=['catalogos-api.js','listados-ui.js','login-ui.js']
FRONT_EXISTING=['assets/js/'+n for n in FRONT_COPY_ONLY+FRONT_SURGICAL+FRONT_OTHER]
FRONT_NEW=[
 'assets/js/rut-chileno-v39.js','assets/js/giros-sii-v39.js',
 'assets/js/buscador-productos-v39.js','assets/css/acciones-buscadores-v39.css'
]
def git_blob(repo: pathlib.Path, sha: str, path: str)->bytes:
 return subprocess.check_output(['git','-C',str(repo),'show',sha+':'+path])
def fail(message):
 raise SystemExit('V39 STOP sin publicación: '+message)
def file(path):return (ROOT/path)
def run():
 # Reconstituir V38 + copyprod publicadas y comparar bytes antes de cambiar nada.
 ns=runpy.run_path(str(BACK/'scripts/v38_limpieza_copy_prod.py'))
 surgical=ns['SURGICAL']
 for relative in FRONT_EXISTING:
  name=pathlib.Path(relative).name
  content=file('before_front/'+relative).read_bytes()
  base=git_blob(FRONT,FRONT_BASE,relative)
  if name in FRONT_COPY_ONLY:
   expected=git_blob(FRONT,FRONT_COPY,relative)
  elif name in FRONT_SURGICAL:
   baseline=base.decode('utf8')
   for old,new in surgical[name]:
    if baseline.count(old)!=1:fail('la fuente V38 contiene texto duplicado '+name)
    baseline=baseline.replace(old,new)
   expected=baseline.encode('utf8')
  else:expected=base
  if content!=expected:fail('fuente publicada modificada respecto a la auditada: '+relative)
  out=file('after_front/'+relative);out.parent.mkdir(parents=True,exist_ok=True)
  out.write_bytes((FRONT/relative).read_bytes())
  print('FRONT_EXISTING_VERIFIED',relative)
 for relative in FRONT_NEW:
  source=FRONT/relative
  if not source.exists() or not source.stat().st_size:fail('componente inexistente '+relative)
  out=file('after_front/'+relative);out.parent.mkdir(parents=True,exist_ok=True)
  out.write_bytes(source.read_bytes())
  print('FRONT_NEW',relative)
 for relative in BACK_EXISTING:
  source=BACK/relative
  target=file('before_back/'+relative)
  base=git_blob(BACK,BACK_BASE,relative)
  if relative=='app/Models/Proveedor.php':
   if target.read_bytes()!=base:fail('Proveedor.php diverge del modelo V38')
   value=source.read_bytes()
  else:
   basefile=file('baseline.tmp');ours=file('feature.tmp')
   basefile.write_bytes(base);ours.write_bytes(source.read_bytes())
   merge=subprocess.run(['git','merge-file','-p',str(target),str(basefile),str(ours)],
      capture_output=True)
   if merge.returncode:
    fail('conflicto real en '+relative+' (estado '+str(merge.returncode)+')')
   value=merge.stdout
   if b'<<<<<<< ' in value or b'>>>>>>> ' in value:
    fail('marcadores conflictivos en '+relative)
  out=file('after_back/'+relative);out.parent.mkdir(parents=True,exist_ok=True)
  out.write_bytes(value)
  print('BACK_INTEGRATED',relative)
 for relative in BACK_NEW:
  src=BACK/relative
  if not src.exists():fail('controlador faltante '+relative)
  out=file('after_back/'+relative);out.parent.mkdir(parents=True,exist_ok=True)
  out.write_bytes(src.read_bytes())
  print('BACK_NEW',relative)
 live=file('before_front/index.html').read_text('utf8')
 target=(FRONT/'index.html').read_text('utf8')
 if 'rut-chileno-v39.js' in live or 'giros-sii-v39.js' in live:
  fail('V39 ya existe en el HTML publicado: evitar aplicar dos veces')
 for key in [
  'hd-branding/logo-hd-site.png','productos-costo-promedio-v37.css',
  'proveedores-ubicaciones-v38.css','ubicacionesSubnav','copyprod38']:
  if key not in live:fail('falta marcador de producción '+key)
 for relative in FRONT_EXISTING:
  name=pathlib.Path(relative).name
  pattern=r'(assets/js/'+re.escape(name)+r')(?:\?v=[^"]+)?(?=")'
  updated,count=re.subn(pattern,
    lambda m:m.group(1)+'?v=20261122-v39prod',live)
  if count!=1:fail('se esperaba un script único '+name+' (total '+str(count)+')')
  live=updated
 anchor='<link rel="stylesheet" href="assets/css/proveedores-ubicaciones-v38.css?v=20261122-geo38">'
 if live.count(anchor)!=1:fail('ancla de CSS V38 incorrecta')
 css='<link rel="stylesheet" href="assets/css/acciones-buscadores-v39.css?v=20261122-v39prod">'
 live=live.replace(anchor,anchor+'\n  '+css)
 scripts=[
  '<script src="assets/js/rut-chileno-v39.js?v=20261122-v39prod" defer></script>',
  '<script src="assets/js/giros-sii-v39.js?v=20261122-v39prod" defer></script>',
  '<script src="assets/js/buscador-productos-v39.js?v=20261122-v39prod" defer></script>'
 ]
 rx=r'(?=  <script src="assets/js/ubicaciones.js)'
 if len(re.findall(rx,live))!=1:fail('ancla de carga de módulos geográficos inesperada')
 live=re.sub(rx,'\n'.join('  '+s for s in scripts)+'\n',live,count=1)
 for tag in scripts:
  if live.count(tag)!=1:fail('script V39 insertado incorrectamente')
 if 'Administración MySQL' in live or 'Servidor Laravel · Acceso JWT protegido' in live:
  fail('el HTML conserva textos técnicos obsoletos')
 out=file('after_front/index.html');out.parent.mkdir(parents=True,exist_ok=True)
 out.write_text(live,encoding='utf-8')
 print('V39_READY: backend=5 frontend=21 (16 existentes+4 nuevos+index), sin SQL')
if __name__=='__main__':run()
