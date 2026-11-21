#!/usr/bin/env python3
"""Pruebas sin credenciales reales ni conexiones a producción."""
from __future__ import annotations
import os
import pathlib
import subprocess
import sys
import tempfile

TOOL = pathlib.Path(__file__).resolve().parents[1] / "tools" / "actualizar_env_smtp_gmail.py"
ORIGINAL = (
    'APP_ENV="production"\nAPP_DEBUG="false"\n'
    'APP_URL="https://rutahyd2.free.nf"\n'
    'DB_DATABASE="if0_43460400_rutahyd2_es"\n'
    'RUTAHD_JWT_SECRET="simulacion-no-es-secreto-real"\n'
    'APP_KEY="base64:simulacion-no-real"\n'
    'MAIL_MAILER="log"\nMAIL_HOST="127.0.0.1"\n'
)


def run_config(credential: str) -> tuple[subprocess.CompletedProcess[str], str]:
    with tempfile.TemporaryDirectory() as d:
        src, dst = pathlib.Path(d) / "origin.env", pathlib.Path(d) / "candidate.env"
        src.write_text(ORIGINAL, encoding="utf-8")
        env = dict(os.environ, RUTAHD_GMAIL_APP_PASSWORD=credential)
        p = subprocess.run([sys.executable, str(TOOL), str(src), str(dst)],
                           capture_output=True, text=True, env=env, check=False)
        return p, dst.read_text(encoding="utf-8") if dst.exists() else ""


def main() -> None:
    fake = "abcd efgh ijkl mnop"
    p, result = run_config(fake)
    assert p.returncode == 0, p.stderr
    assert 'MAIL_USERNAME="neo.hiper.xavier@gmail.com"' in result
    assert 'MAIL_HOST="smtp.gmail.com"' in result
    assert 'MAIL_PORT="587"' in result
    assert 'MAIL_ENCRYPTION="tls"' in result
    assert 'MAIL_MAILER="smtp"' in result
    assert 'MAIL_PASSWORD="abcdefghijklmnopq"' not in result
    assert 'MAIL_PASSWORD="abcdefghijklmnop"' in result
    assert 'APP_KEY="base64:simulacion-no-real"' in result
    assert 'RUTAHD_JWT_SECRET="simulacion-no-es-secreto-real"' in result
    assert 'MAIL_MAILER="log"' not in result
    assert fake not in p.stdout + p.stderr
    p2, result2 = run_config("")
    assert p2.returncode != 0 and result2 == ""
    print("OK: correo configurado, secretos no registrados, claves previas preservadas, sin secret no hay escritura.")


if __name__ == "__main__":
    main()
