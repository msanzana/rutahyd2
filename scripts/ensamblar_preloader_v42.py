#!/usr/bin/env python3
"""Ruta H&D V42: parche exacto de producción con popup de espera.

Usar solo con archivos FTPS descargados en /tmp/preloader-v42/old.
Preserva por completo el HTML productivo V41, salvo 3 referencias explícitas.
"""
from pathlib import Path
import os,re,subprocess
ROOT=Path('/tmp/preloader-v42')
FRONT=Path('/tmp/frontend-v42')
BEFORE=os.environ['FRONT_BEFORE']
AFTER=os.environ['FRONT_AFTER']
MAIN='assets/js/catalogos-api.js'
NEW=['assets/js/carga-global-v42.js','assets/css/carga-global-v42.css']
def git(commit,path):
    return subprocess.check_output(['git','-C',str(FRONT),'show',commit+':'+path])
def ensure(cond,msg):
    if not cond:raise SystemExit('V42 STOP sin publicar: '+msg)
def main():
    old=(ROOT/'old'/MAIN).read_bytes()
    ensure(old==git(BEFORE,MAIN),'catalogos-api productivo no coincide con V41')
    target=git(AFTER,MAIN)
    ensure(old!=target and b'RutaHDCarga?.start' in target,'enlace de API a preload ausente')
    out=ROOT/'new'/MAIN;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(target)
    for path in NEW:
        content=git(AFTER,path)
        ensure(len(content)>100,'recurso V42 vacío '+path)
        dest=ROOT/'new'/path;dest.parent.mkdir(parents=True,exist_ok=True)
        dest.write_bytes(content)
    html=(ROOT/'old/index.html').read_text('utf-8')
    for marker in [
        'hd-branding/logo-hd-site.png','ubicacionesSubnav',
        'giros-sii-v39.js?v=20261122-v41prod',
        'mix-proveedores-v40.css','proveedores-ubicaciones-v38.css']:
        ensure(marker in html,'faltan elementos productivos '+marker)
    ensure('carga-global-v42' not in html,'preloader ya está publicado')
    css=r'(<link rel="stylesheet" href="assets/css/mix-proveedores-v40\.css[^"]*">)'
    html,n=re.subn(css,lambda m:m.group(1)+
      '\n  <link rel="stylesheet" href="assets/css/carga-global-v42.css?v=20261122-v42prod">',html)
    ensure(n==1,'ancla CSS V40 cambió')
    js=r'(^\s*<script src="assets/js/catalogos-api\.js[^"]*" defer></script>)'
    def scripts(m):
        return ('  <script src="assets/js/carga-global-v42.js?v=20261122-v42prod" defer></script>\n'
                '  <script src="assets/js/catalogos-api.js?v=20261122-v42prod" defer></script>')
    html,n=re.subn(js,scripts,html,flags=re.MULTILINE)
    ensure(n==1,'ancla JS de API cambió')
    ensure(html.count('carga-global-v42.js')==1 and html.count('carga-global-v42.css')==1,
        'referencias del popup duplicadas')
    ensure(html.index('carga-global-v42.js')<html.index('catalogos-api.js'),
        'preloader debe cargarse antes de API')
    ensure('Administración MySQL' not in html,'texto técnico anterior reapareció')
    target=ROOT/'new/index.html';target.parent.mkdir(parents=True,exist_ok=True)
    target.write_text(html,'utf-8')
    print('V42_READY: catalogos-api.js, 2 recursos nuevos e index.html, 0 cambios SQL')
if __name__=='__main__':main()
