#!/usr/bin/env python3
"""
Genera database/seed.sql a partir de data/negocios.json y data/blog.json.
Se ejecuta una sola vez (o cada vez que se actualicen los JSON de ejemplo)
para regenerar los datos iniciales de la base de datos. No se necesita en
producción: el resultado ya queda incluido en schema.sql.

Uso: python3 database/generate_seed.py
"""
import json
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def esc(value):
    """Escapa un string para un literal SQL entre comillas simples."""
    if value is None:
        return "NULL"
    s = str(value)
    s = s.replace("\\", "\\\\").replace("'", "''")
    return "'" + s + "'"


def num_or_null(value):
    if value is None:
        return "NULL"
    return str(value)


def bool_sql(value):
    return "1" if value else "0"


def main():
    with open(os.path.join(BASE, "data", "negocios.json"), encoding="utf-8") as f:
        negocios = json.load(f)
    with open(os.path.join(BASE, "data", "blog.json"), encoding="utf-8") as f:
        articulos = json.load(f)

    lines = []
    lines.append("-- =========================================================================")
    lines.append("-- UNE Sports — Datos iniciales (generado automáticamente desde data/*.json)")
    lines.append("-- No editar a mano: volver a correr database/generate_seed.py si cambian los")
    lines.append("-- JSON de ejemplo en /data.")
    lines.append("-- =========================================================================")
    lines.append("")
    lines.append("SET NAMES utf8mb4;")
    lines.append("SET FOREIGN_KEY_CHECKS = 0;")
    lines.append("")

    # --- Categorías (a partir de tipo/tipoLabel únicos en negocios.json) ---
    categorias = {}
    for n in negocios:
        categorias[n["tipo"]] = n["tipoLabel"]

    # Se ignora lo derivado arriba: el catálogo de categorías es una lista
    # fija de 13 tipos de negocio + "Otro" (no depende de qué negocios de
    # ejemplo existan), para que el formulario de registro y los filtros
    # siempre muestren las 14 opciones completas.
    categorias = {
        "academia-deportiva-formativa": "Academia Deportiva Formativa",
        "escuela-deportiva-formativa": "Escuela Deportiva Formativa",
        "rehabilitacion-fisioterapia": "Centros de Rehabilitación y Fisioterapia Deportiva",
        "medicina-deportiva-pediatrica": "Clínicas de Medicina Deportiva Pediátrica",
        "nutricion-dietetica-deportiva": "Centros de Nutrición y Dietética Deportiva",
        "biomecanica-deportiva": "Laboratorios de Biomecánica Deportiva",
        "psicologia-deportiva": "Centros de Psicología Deportiva",
        "coaching-liderazgo": "Consultoras de Coaching Deportivo y Liderazgo",
        "intervencion-familiar": "Organizaciones de Intervención Familiar",
        "tutoria-nivelacion": "Centros de Tutoría y Nivelación Académica",
        "safeguarding": "Agencias de Safeguarding (Protección Infantil en el Deporte)",
        "ong-desarrollo-deporte": "ONGs de Desarrollo a través del Deporte",
        "derecho-deportivo": "Estudios de Derecho Deportivo",
        "otro": "Otro",
    }

    lines.append("-- Categorías de negocio (lista fija)")
    lines.append("INSERT INTO categorias (slug, nombre) VALUES")
    cat_rows = [f"  ({esc(slug)}, {esc(nombre)})" for slug, nombre in categorias.items()]
    lines.append(",\n".join(cat_rows) + ";")
    lines.append("")

    cat_id_by_slug = {slug: i + 1 for i, slug in enumerate(categorias.keys())}

    # --- Etapas del deporte formativo (catálogo fijo de 5 rangos de edad) ---
    ETAPAS = ["4 a 6 años", "7 a 9 años", "10 a 12 años", "13 a 15 años", "16 a 18 años"]
    lines.append("-- Etapas del deporte formativo (rangos de edad, lista fija)")
    lines.append("INSERT INTO etapas (nombre, orden) VALUES")
    lines.append(",\n".join(f"  ({esc(e)}, {i})" for i, e in enumerate(ETAPAS)) + ";")
    lines.append("")
    etapa_id_by_name = {name: i + 1 for i, name in enumerate(ETAPAS)}

    # --- Servicios (catálogo único) ---
    servicios_set = []
    seen_s = set()
    for n in negocios:
        for s in n.get("servicios", []):
            if s not in seen_s:
                seen_s.add(s)
                servicios_set.append(s)

    lines.append("-- Catálogo de servicios/especialidades")
    lines.append("INSERT INTO servicios (nombre) VALUES")
    serv_rows = [f"  ({esc(s)})" for s in servicios_set]
    lines.append(",\n".join(serv_rows) + ";")
    lines.append("")

    serv_id_by_name = {name: i + 1 for i, name in enumerate(servicios_set)}

    # --- Negocios ---
    lines.append("-- Negocios")
    lines.append(
        "INSERT INTO negocios "
        "(id, slug, nombre, categoria_id, region, provincia, distrito, direccion, telefono, "
        "whatsapp, email, precio, descripcion, imagen_principal, contacto_nombre, contacto_cargo, "
        "contacto_foto, destacado, verificado, estado, valoracion_promedio, total_resenas, lat, lng) VALUES"
    )
    neg_rows = []
    for n in negocios:
        neg_rows.append(
            "  (" + ", ".join([
                num_or_null(n["id"]),
                esc(n["slug"]),
                esc(n["nombre"]),
                num_or_null(cat_id_by_slug[n["tipo"]]),
                esc(n["region"]),
                esc(n["provincia"]),
                esc(n["distrito"]),
                esc(n["direccion"]),
                esc(n["telefono"]),
                esc(n["whatsapp"]),
                esc(n["email"]),
                esc(n["precio"]),
                esc(n["descripcion"]),
                esc(n["imagenPrincipal"]),
                esc(n["contacto"]["nombre"]),
                esc(n["contacto"]["cargo"]),
                esc(n["contacto"]["foto"]),
                bool_sql(n.get("destacado")),
                bool_sql(n.get("verificado")),
                "'publicado'",
                num_or_null(n["valoracion"]),
                num_or_null(n["numResenas"]),
                num_or_null(n["lat"]),
                num_or_null(n["lng"]),
            ]) + ")"
        )
    lines.append(",\n".join(neg_rows) + ";")
    lines.append("")

    # --- negocio_servicios ---
    lines.append("-- Relación negocio <-> servicios")
    ns_rows = []
    for n in negocios:
        for i, s in enumerate(n.get("servicios", [])):
            ns_rows.append(f"  ({n['id']}, {serv_id_by_name[s]}, {i})")
    if ns_rows:
        lines.append("INSERT INTO negocio_servicios (negocio_id, servicio_id, orden) VALUES")
        lines.append(",\n".join(ns_rows) + ";")
        lines.append("")

    # --- negocio_etapas ---
    ne_rows = []
    for n in negocios:
        for etapa in n.get("etapas", []):
            ne_rows.append(f"  ({n['id']}, {etapa_id_by_name[etapa]})")
    if ne_rows:
        lines.append("-- Relación negocio <-> etapas del deporte formativo")
        lines.append("INSERT INTO negocio_etapas (negocio_id, etapa_id) VALUES")
        lines.append(",\n".join(ne_rows) + ";")
        lines.append("")

    # --- negocio_imagenes ---
    lines.append("-- Galería de imágenes")
    img_rows = []
    for n in negocios:
        for i, url in enumerate(n.get("galeria", [])):
            img_rows.append(f"  ({n['id']}, {esc(url)}, {i})")
    lines.append("INSERT INTO negocio_imagenes (negocio_id, url, orden) VALUES")
    lines.append(",\n".join(img_rows) + ";")
    lines.append("")

    # --- negocio_horarios ---
    lines.append("-- Horarios de atención")
    hor_rows = []
    for n in negocios:
        for i, h in enumerate(n.get("horario", [])):
            hor_rows.append(f"  ({n['id']}, {esc(h['dia'])}, {esc(h['hora'])}, {i})")
    lines.append("INSERT INTO negocio_horarios (negocio_id, dia, hora, orden) VALUES")
    lines.append(",\n".join(hor_rows) + ";")
    lines.append("")

    # --- valoraciones ---
    lines.append("-- Reseñas de ejemplo (el promedio/total mostrado en el negocio ya incluye")
    lines.append("-- reseñas históricas adicionales no listadas individualmente aquí)")
    val_rows = []
    for n in negocios:
        for r in n.get("resenas", []):
            val_rows.append(
                "  (" + ", ".join([
                    num_or_null(n["id"]),
                    esc(r["autor"]),
                    esc(r.get("avatar")),
                    num_or_null(r["valoracion"]),
                    esc(r["comentario"]),
                    esc(r["fecha"] + " 12:00:00"),
                ]) + ")"
            )
    lines.append("INSERT INTO valoraciones (negocio_id, usuario_nombre, usuario_avatar, puntuacion, comentario, creado_en) VALUES")
    lines.append(",\n".join(val_rows) + ";")
    lines.append("")

    # --- blog_categorias ---
    blog_cats = []
    seen_bc = set()
    for a in articulos:
        if a["categoria"] not in seen_bc:
            seen_bc.add(a["categoria"])
            blog_cats.append(a["categoria"])

    lines.append("-- Categorías del blog")
    lines.append("INSERT INTO blog_categorias (nombre) VALUES")
    lines.append(",\n".join(f"  ({esc(c)})" for c in blog_cats) + ";")
    lines.append("")

    blog_cat_id = {name: i + 1 for i, name in enumerate(blog_cats)}

    # --- blog_articulos ---
    lines.append("-- Artículos del blog")
    lines.append(
        "INSERT INTO blog_articulos "
        "(id, slug, titulo, categoria_id, resumen, imagen, autor_nombre, autor_foto, "
        "fecha_publicacion, tiempo_lectura, contenido) VALUES"
    )
    art_rows = []
    for a in articulos:
        contenido_json = json.dumps(a["contenido"], ensure_ascii=False)
        art_rows.append(
            "  (" + ", ".join([
                num_or_null(a["id"]),
                esc(a["slug"]),
                esc(a["titulo"]),
                num_or_null(blog_cat_id[a["categoria"]]),
                esc(a["resumen"]),
                esc(a["imagen"]),
                esc(a["autor"]),
                esc(a["autorFoto"]),
                esc(a["fecha"]),
                esc(a["tiempoLectura"]),
                esc(contenido_json),
            ]) + ")"
        )
    lines.append(",\n".join(art_rows) + ";")
    lines.append("")

    lines.append("-- Ajustar los AUTO_INCREMENT para que sigan después de los IDs sembrados")
    lines.append(f"ALTER TABLE negocios AUTO_INCREMENT = {len(negocios) + 1};")
    lines.append(f"ALTER TABLE blog_articulos AUTO_INCREMENT = {len(articulos) + 1};")
    lines.append("")
    lines.append("SET FOREIGN_KEY_CHECKS = 1;")
    lines.append("")

    out_path = os.path.join(BASE, "database", "seed.sql")
    with open(out_path, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))
    print("Generado:", out_path)


if __name__ == "__main__":
    main()
