#!/usr/bin/env python3
"""Ruta H&D · parche EXCLUSIVAMENTE de etiquetas para la versión productiva V38.

Entrada: archivos FTPS descargados en /tmp/copyprod/previous y fuente V39 en
/tmp/rv39front. Salida: /tmp/copyprod/next para despliegue controlado con rollback.
Si cualquier ancla difiere, no genera un despliegue parcial.
No contiene SQL ni modifica ningún archivo en el alojamiento directamente.
"""
from __future__ import annotations
import pathlib
import re
import shutil
import subprocess

ROOT = pathlib.Path("/tmp/copyprod")
FRONT = pathlib.Path("/tmp/rv39front")
BASE = "a36bc7b81d653061ed3b2646091d31a2d5c9fbbc"
FILES = [
    "admin-remoto.js", "app-produccion.js", "galeria-productos.js",
    "listas-precios.js", "login-produccion.js", "menu-usuario.js",
    "perfiles.js", "productos-remotos.js", "recuperacion.js", "usuarios.js",
    "modulos-oficiales.js", "proveedores-productos.js", "ventas-precios.js",
]
# Sólo estos diez archivos cambiaron en V39 por mensajes informativos.
# Los otros tres contienen funciones nuevas V39: actualizar únicamente
# sus textos para evitar llamar endpoints sin DDL33 y DDL34.
COPY_ONLY = set(FILES[:10])
SURGICAL = {
    "modulos-oficiales.js": [
        ("El servidor aún no tiene habilitada la selección de listas. Los datos del cliente siguen editables.",
         "La selección de listas de precios no está disponible. Puedes seguir editando los datos del cliente."),
        ("Guardando en el servidor…", "Guardando cambios…"),
        ("El backend no tiene todavía un contrato comprobado de cuentas corrientes. No se mostrarán saldos estimados.",
         "Este módulo aún no está disponible. No se mostrarán saldos estimados."),
        ("No existen registros para esta página de la base de datos.",
         "No hay registros para mostrar en esta página."),
    ],
    "proveedores-productos.js": [
        ("Guardando en Laravel…", "Guardando cambios…"),
        ("Cargando directorio de proveedores desde Laravel…",
         "Cargando directorio de proveedores…"),
    ],
    "ventas-precios.js": [
        ("IVA según condición tributaria; Laravel recalculará el total.",
         "IVA según condición tributaria. El total definitivo se confirmará al registrar la venta."),
    ],
}
HTML = [
    ("Servidor Laravel · Acceso JWT protegido", "Acceso autorizado · Ruta H&D"),
    ("<strong>Sistema productivo:</strong> todos los registros provienen del servidor. Debes ingresar con tu cuenta autorizada.",
     "<strong>Bienvenido a Ruta H&D.</strong> Ingresa para gestionar tu información comercial."),
    ("Información del servidor autenticado.", "Consulta y administra tus registros."),
    ("Se comprobarán tus permisos en el servidor.",
     "Confirma que deseas continuar con esta operación."),
    ("La información se consulta y modifica exclusivamente mediante Laravel y MariaDB.",
     "Administra la información de tu empresa según los permisos de tu perfil."),
    ("Accede con tu cuenta autorizada. La aplicación comprueba la conexión Laravel antes de enviar tus credenciales.",
     "Ingresa con tu cuenta para acceder a las herramientas de gestión."),
]

def replace_once(data: str, before: str, after: str, context: str) -> str:
    count = data.count(before)
    if count != 1:
        raise RuntimeError(f"STOP: ancla {context!r} no es única (encontradas {count})")
    return data.replace(before, after)

def main() -> None:
    (ROOT / "next/assets/js").mkdir(parents=True, exist_ok=True)
    for name in FILES:
        path = f"assets/js/{name}"
        remote = (ROOT / "previous" / path).read_bytes()
        base = subprocess.check_output(
            ["git", "-C", str(FRONT), "show", f"{BASE}:{path}"]
        )
        if remote != base:
            raise RuntimeError(f"STOP: archivo productivo distinto de V38: {path}")
        destination = ROOT / "next" / path
        if name in COPY_ONLY:
            target = (FRONT / path).read_bytes()
            if not target or target == base:
                raise RuntimeError(f"STOP: fuente sin limpieza: {path}")
            destination.write_bytes(target)
            print("COPIA_UI_V39_SIN_FUNCIONES_NUEVAS", path)
        else:
            value = remote.decode("utf-8")
            for before, after in SURGICAL[name]:
                value = replace_once(value, before, after, path)
            destination.write_text(value, encoding="utf-8")
            print("CAMBIO_LITERAL_EN_V38", path, len(SURGICAL[name]), "etiquetas")
    old_html = (ROOT / "previous/index.html").read_text(encoding="utf-8")
    new_html = old_html
    for a,b in HTML:
        new_html = replace_once(new_html,a,b,"index.html")
    for name in FILES:
        pattern = (
            r'(<script\s+src="assets/js/' + re.escape(name) +
            r')(?:\?v=[^"]*)?("\s+defer></script>)'
        )
        new_html, count = re.subn(
            pattern, lambda m: m.group(1) + "?v=20261122-copyprod38" + m.group(2),
            new_html,
        )
        if count != 1:
            raise RuntimeError(f"STOP: referencia duplicada o ausente para {name}: {count}")
    for marker in [
        "hd-branding/logo-hd-site.png",
        "productos-costo-promedio-v37.css",
        'id="ubicacionesSubnav"',
        "proveedores-ubicaciones-v38.css",
    ]:
        if marker not in new_html:
            raise RuntimeError(f"STOP: falta protección del diseño anterior: {marker}")
    if "rut-chileno-v39.js" in new_html or "buscador-productos-v39.js" in new_html:
        raise RuntimeError("STOP: no publicar JS de V39 antes de importar sus DDL")
    (ROOT / "next/index.html").write_text(new_html, encoding="utf-8")
    after=(ROOT/"next/assets/js/admin-remoto.js").read_text(encoding="utf-8")
    for forbidden in [
        "Administración MySQL", "CONEXIÓN SEGURA",
        "Los cambios se guardan en MariaDB",
        "Consultar MySQL", "Consultas reales del servidor",
    ]:
        if forbidden in after:
            raise RuntimeError(f"STOP: texto técnico pendiente: {forbidden}")
    if "Administración" not in after or "Organiza y actualiza los catálogos" not in after:
        raise RuntimeError("STOP: nuevo encabezado no presente")
    print("COPYPROD38_LISTO: 13 JS, un HTML, 0 modificaciones SQL")

if __name__ == "__main__":
    main()
