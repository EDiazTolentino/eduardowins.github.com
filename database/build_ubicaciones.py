#!/usr/bin/env python3
"""
Construye database/ubicaciones_peru.sql (catálogo completo de región /
provincia / distrito del Perú) a partir de los CSV descargados de
https://github.com/RitchieRD/ubigeos-peru-data (25 departamentos/regiones,
196 provincias, 1892 distritos, con Callao ya separado de Lima).

Este script se corrió una sola vez con los CSV en /tmp; el resultado ya
quedó guardado en database/ubicaciones_peru.sql, así que no hace falta
volver a ejecutarlo salvo que se quiera regenerar el catálogo.
"""
import csv
import re

DEPARTAMENTOS_CSV = "/tmp/1_ubigeo_departamentos.csv"
PROVINCIAS_CSV = "/tmp/2_ubigeo_provincias.csv"
DISTRITOS_CSV = "/tmp/3_ubigeo_distritos.csv"
OUT_SQL = "/home/user/eduardowins.github.com/database/ubicaciones_peru.sql"

# El dataset trae los nombres en MAYÚSCULAS y sin tildes; corregimos las
# tildes solo a nivel de región (25 nombres, fácil de verificar a mano).
ACCENT_FIX = {
    "Ancash": "Áncash",
    "Apurimac": "Apurímac",
    "Huanuco": "Huánuco",
    "Junin": "Junín",
    "San Martin": "San Martín",
}

STOPWORDS = {"de", "del", "la", "las", "los", "y"}


def smart_title(text: str) -> str:
    words = text.strip().lower().split(" ")
    out = []
    for i, w in enumerate(words):
        if i > 0 and w in STOPWORDS:
            out.append(w)
        else:
            out.append(w[:1].upper() + w[1:] if w else w)
    result = " ".join(out)
    return ACCENT_FIX.get(result, result)


def esc(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def main():
    with open(DEPARTAMENTOS_CSV, encoding="utf-8") as f:
        departamentos = {row["id"]: smart_title(row["departamento"]) for row in csv.DictReader(f)}

    with open(PROVINCIAS_CSV, encoding="utf-8") as f:
        provincias = {}
        for row in csv.DictReader(f):
            provincias[row["id"]] = {
                "nombre": smart_title(row["provincia"]),
                "departamento_id": row["departamento_id"],
            }

    with open(DISTRITOS_CSV, encoding="utf-8") as f:
        distritos = list(csv.DictReader(f))

    rows = []
    for d in distritos:
        prov = provincias[d["provincia_id"]]
        region = departamentos[prov["departamento_id"]]
        provincia = prov["nombre"]
        distrito = smart_title(d["distrito"])
        rows.append((region, provincia, distrito))

    rows.sort()

    with open(OUT_SQL, "w", encoding="utf-8") as f:
        f.write(
            "-- =========================================================================\n"
            "-- UNE Sports — Catálogo completo región / provincia / distrito del Perú\n"
            "-- Fuente: https://github.com/RitchieRD/ubigeos-peru-data (25 regiones,\n"
            "-- separando Callao de Lima; nombres normalizados a Capitalización estándar).\n"
            "-- Seguro de ejecutar sobre una base ya en producción: solo reemplaza el\n"
            "-- contenido de distritos_peru, no toca negocios/blog/reseñas/mensajes.\n"
            "-- =========================================================================\n\n"
            "SET NAMES utf8mb4;\n"
            "DELETE FROM distritos_peru;\n"
            "ALTER TABLE distritos_peru AUTO_INCREMENT = 1;\n\n"
            "INSERT INTO distritos_peru (region, provincia, distrito) VALUES\n"
        )
        lines = [f"  ({esc(r)}, {esc(p)}, {esc(di)})" for r, p, di in rows]
        f.write(",\n".join(lines))
        f.write(";\n")

    print(f"Generado {OUT_SQL} con {len(rows)} distritos.")


if __name__ == "__main__":
    main()
