#!/usr/bin/env python3
"""Actualiza solo variables MAIL_* del .env privado. No imprime secretos."""
from __future__ import annotations

import json
import os
import re
import sys
from pathlib import Path

MAIL_KEYS = (
    "MAIL_MAILER", "MAIL_HOST", "MAIL_PORT", "MAIL_ENCRYPTION",
    "MAIL_USERNAME", "MAIL_PASSWORD", "MAIL_FROM_ADDRESS", "MAIL_FROM_NAME"
)


def parse_value(raw: str) -> str:
    raw = raw.strip()
    try:
        parsed = json.loads(raw)
        return str(parsed).lower() if isinstance(parsed, bool) else str(parsed)
    except (ValueError, TypeError):
        return raw.strip("'\"")


def main() -> int:
    if len(sys.argv) != 3:
        raise SystemExit("Uso: actualizar_env_smtp_gmail.py ENTRADA SALIDA")
    source, destination = map(Path, sys.argv[1:])
    password = "".join(os.environ.get("RUTAHD_GMAIL_APP_PASSWORD", "").split())
    if re.fullmatch(r"[A-Za-z0-9]{16}", password) is None:
        raise SystemExit("No hay contraseña de aplicación válida: registrar el secreto privado RUTAHD_GMAIL_APP_PASSWORD.")
    text = source.read_text(encoding="utf-8")
    original: dict[str, str] = {}
    output: list[str] = []
    for line in text.splitlines(keepends=True):
        match = re.match(r"^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$", line)
        if match:
            key, value = match.groups()
            if key in original:
                raise SystemExit("La configuración tiene claves duplicadas; revisar antes de actualizar.")
            original[key] = parse_value(value)
            if key in MAIL_KEYS:
                continue
        output.append(line)

    if original.get("APP_ENV") != "production":
        raise SystemExit("APP_ENV no está configurado como production.")
    if original.get("APP_DEBUG", "").lower() != "false":
        raise SystemExit("APP_DEBUG debe ser false.")
    if original.get("DB_DATABASE") != "if0_43460400_rutahyd2_es":
        raise SystemExit("La base conectada no es la base española autorizada.")
    if original.get("APP_URL") != "https://rutahyd2.free.nf":
        raise SystemExit("APP_URL productiva no coincide.")
    if not original.get("RUTAHD_JWT_SECRET"):
        raise SystemExit("Falta el secreto JWT vigente; no tocar el servidor.")

    values = {
        "MAIL_MAILER": "smtp",
        "MAIL_HOST": "smtp.gmail.com",
        "MAIL_PORT": "587",
        "MAIL_ENCRYPTION": "tls",
        "MAIL_USERNAME": "neo.hiper.xavier@gmail.com",
        "MAIL_PASSWORD": password,
        "MAIL_FROM_ADDRESS": "neo.hiper.xavier@gmail.com",
        "MAIL_FROM_NAME": "Ruta H&D V2",
    }
    if output and not output[-1].endswith("\n"):
        output.append("\n")
    output.append("\n# Correo saliente SMTP de soporte (secreto fuera de GitHub).\n")
    for key, value in values.items():
        output.append(f"{key}={json.dumps(value, ensure_ascii=False)}\n")

    destination.write_text("".join(output), encoding="utf-8")
    destination.chmod(0o600)
    # No imprimir la contraseña, su hash, el contenido ni rutas del .env.
    print("Candidato SMTP preparado conservando las claves existentes. Sin datos sensibles en logs.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
