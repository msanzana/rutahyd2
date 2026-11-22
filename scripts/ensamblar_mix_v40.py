#!/usr/bin/env python3
"""Preparar V40 con cambios mínimos, preservando V39 publicado y hotfixes.
Sin conexión a bases de datos ni escritura de FTPS.
"""
from __future__ import annotations
import pathlib,subprocess,re,os
ROOT=pathlib.Path('/tmp/v40')
BACK=pathlib.Path('/tmp/v40back')
FRONT=pathlib.Path('/tmp/v40front')
BASE_BACK=os.environ['BACK_BASE']
BASE_FRONT=os.environ['FRONT_BASE']
BACK_FILES=[
    'routes/api.php',
    'app/Http/Controllers/ProductosProveedoresControlador.php',
]
FRONT_FILES=[
    'assets/js/proveedores-productos.js',
    'assets/js/catalogos-api.js',
]
NEW_FILES=['assets/css/mix-proveedores-v40.css']
def git(repo,revision,path):
    return subprocess.check_output(['git','-C',str(repo),'show',revision+':'+path])
def assemble():
    for path in BACK_FILES:
        before=ROOT/'before_back'/path
        source=BACK/path
        base=ROOT/'base.tmp'
        new=ROOT/'ours.tmp'
        base.write_bytes(git(BACK,BASE_BACK,path))
        new.write_bytes(source.read_bytes())
        merge=subprocess.run(['git','merge-file','-p',str(before),str(base),str(new)],
            capture_output=True)
        if merge.returncode or b'<<<<<<<' in merge.stdout:
            raise SystemExit('V40 STOP: conflicto PHP con hotfix productivo '+path)
        target=ROOT/'after_back'/path
        target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes(merge.stdout)
        print('PHP_MERGE_LIMPIO',path)
    for path in FRONT_FILES:
        deployed=(ROOT/'before_front'/path).read_bytes()
        baseline=git(FRONT,BASE_FRONT,path)
        if deployed!=baseline:
            raise SystemExit('V40 STOP: JavaScript publicado cambió desde V39: '+path)
        target=ROOT/'after_front'/path
        target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes((FRONT/path).read_bytes())
        print('FRONT_BASE_CONFIRMED',path)
    for path in NEW_FILES:
        source=FRONT/path
        if not source.exists() or source.stat().st_size<100:
            raise SystemExit('V40 STOP: CSS no disponible')
        target=ROOT/'after_front'/path
        target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes(source.read_bytes())
    html=(ROOT/'before_front/index.html').read_text('utf-8')
    for term in ['hd-branding/logo-hd-site.png','ubicacionesSubnav',
                 'productos-costo-promedio-v37.css',
                 'acciones-buscadores-v39.css','proveedores-ubicaciones-v38.css']:
        if term not in html:raise SystemExit('V40 STOP: branding V39 ausente: '+term)
    if 'mix-proveedores-v40.css' in html:raise SystemExit('V40 ya publicado, no sobreescribir')
    css=r'(<link rel="stylesheet" href="assets/css/acciones-buscadores-v39.css[^"]*">)'
    if len(re.findall(css,html))!=1:raise SystemExit('Ancla CSS V39 cambió')
    html=re.sub(css,r'\1'+'\n  <link rel="stylesheet" href="assets/css/mix-proveedores-v40.css?v=20261122-v40prod">',html)
    for name in ['catalogos-api.js','proveedores-productos.js']:
        pattern=r'(assets/js/'+re.escape(name)+r')(?:\?v=[^"]+)?(?=")'
        html,count=re.subn(pattern,lambda match:match.group(1)+'?v=20261122-v40prod',html)
        if count!=1:raise SystemExit('V40 STOP: un script faltante o duplicado '+name)
    if 'Administración MySQL' in html:
        raise SystemExit('V40 STOP: branding comercial anterior ausente')
    target=ROOT/'after_front/index.html'
    target.parent.mkdir(parents=True,exist_ok=True)
    target.write_text(html,'utf-8')
    print('V40_PREPARADO: 2 archivos PHP, 2 JS, CSS nuevo, index.html con versión nueva')
if __name__=='__main__':assemble()
