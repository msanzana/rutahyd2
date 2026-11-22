#!/usr/bin/env python3
"""Generador reproducible DDL34 desde la publicación oficial SII (sin acceso a MySQL).
Nunca usa datos ficticios: exige 674 códigos distintos con estructura de seis dígitos.
"""
from __future__ import annotations
import datetime
import re
import sys
from pathlib import Path
from urllib.request import urlopen, Request
from bs4 import BeautifulSoup

SOURCES=(
    "https://www.sii.cl/ayudas/ayudas_por_servicios/1956-codigos-1959.html",
    "https://www.sii.cl/catastro/codigos.htm",
)
OUTPUT=Path("database/espanol/34_semillas_giros_sii_completo_674.sql")
EXPECTED=674
def clean(text: str)->str:
    return re.sub(r"\s+"," ",text.replace("\xa0"," ")).strip()
def quote(val: str)->str:
    return "'"+val.replace("\\","\\\\").replace("'","''")+"'"
def fetch_page(url):
    req=Request(url,headers={"User-Agent":"Mozilla/5.0 (compatible; RutaHD2Catalogo/1.0)",
        "Accept":"text/html,application/xhtml+xml"})
    with urlopen(req,timeout=35) as res:
        body=res.read()
        content_type=res.headers.get_content_charset()
    for encoding in [content_type,"utf-8","iso-8859-1"]:
        if not encoding:continue
        try:return body.decode(encoding)
        except (UnicodeError,LookupError):pass
    return body.decode("iso-8859-1",errors="replace")
def extract(html: str):
    soup=BeautifulSoup(html,"html.parser")
    rows=[]
    for tr in soup.find_all("tr"):
        cols=tr.find_all("td",recursive=False)
        if len(cols)<2:
            cols=tr.find_all("td")
        if len(cols)<2:continue
        values=[clean(x.get_text(" ",strip=True)) for x in cols]
        code=values[0].replace(" ","")
        if not re.fullmatch(r"\d{6}",code):continue
        if not values[1] or len(values[1])<5:continue
        # Tabla oficial: [Código, Descripción, Afecto IVA, Categoría, Disponible internet]
        iva=values[2].upper() if len(values)>2 else "G"
        cat=values[3].upper() if len(values)>3 else "G"
        internet=values[4].upper() if len(values)>4 else "SI"
        iva=iva if iva in {"SI","NO","G"} else "G"
        cat=cat if cat in {"1","2","G"} else "G"
        internet=internet if internet in {"SI","NO","G"} else "G"
        rows.append((code,values[1],iva,cat,internet))
    return rows
def main():
    best=[]
    chosen=""
    for url in SOURCES:
        try:
            rows=extract(fetch_page(url))
            print("Origen verificado:",url,"registros:",len(rows))
            if len(rows)>len(best):
                chosen=url;best=rows
            if len(rows)==EXPECTED:break
        except Exception as ex:
            print("Advertencia: fuente no disponible",url,type(ex).__name__,str(ex)[:120])
    keys=[r[0] for r in best]
    if len(keys)!=EXPECTED or len(set(keys))!=EXPECTED or any(len(r[1])>400 for r in best):
        raise SystemExit("NO generar DDL34: catálogo no íntegro (674 códigos distintos esperados).")
    if not {"011101","472101","990000"}.issubset(set(keys)):
        raise SystemExit("NO generar DDL34: faltan códigos de control.")
    chunks=[]
    for index in range(0,len(best),80):
        lines=[]
        for code,name,iva,cat,online in best[index:index+80]:
            lines.append(f"  ({quote(code)},{quote(name)},{quote(iva)},{quote(cat)},{quote(online)},1,{quote(chosen)})")
        chunks.append("INSERT INTO giros_sii (codigo,descripcion,afecta_iva,categoria_tributaria,disponible_internet,activo,fuente) VALUES\n"+
            ",\n".join(lines)+"\nON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion),"+
            " afecta_iva=VALUES(afecta_iva),categoria_tributaria=VALUES(categoria_tributaria),"+
            " disponible_internet=VALUES(disponible_internet),fuente=VALUES(fuente);")
    contents=(
        "-- Ruta H&D V39 · DDL34: catálogo íntegro de 674 actividades económicas SII.\n"
        "-- Generado desde el HTML OFICIAL del SII, no inventado ni tomado de datos de contribuyentes.\n"
        f"-- Fuente: {chosen}\n"
        "-- Exigir antes: DDL33, tabla giros_sii. Ejecutar MANUALMENTE en phpMyAdmin.\n"
        "-- CSV/HTML SII no representa la situación tributaria de una empresa específica.\n"
        "-- Código CHAR(6), preservar ceros de la izquierda. No modificar proveedores.\n"
        "SET NAMES utf8mb4;\n\n"+"\n\n".join(chunks)+"\n\n"
        "SELECT COUNT(*) AS codigos_giro_sii_disponibles FROM giros_sii;\n"
    )
    OUTPUT.parent.mkdir(parents=True,exist_ok=True)
    OUTPUT.write_text(contents,encoding="utf-8")
    print("DDL34 generada:",OUTPUT,len(contents),"bytes",len(best),"códigos")
if __name__=="__main__":
    main()
