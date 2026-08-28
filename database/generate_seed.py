#!/usr/bin/env python3
"""
Genera database/seed.sql a partir de data/negocios.json y data/blog.json.
Se ejecuta una sola vez (o cada vez que se actualicen los JSON de ejemplo)
para regenerar los datos iniciales de la base de datos. No se necesita en
producción: el resultado ya queda incluido en database/une_sports.sql.

Uso: python3 database/generate_seed.py
"""
import json
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# --- Catálogos fijos (no dependen de qué negocios de ejemplo existan) ------

CATEGORIAS = {
    "academia-deportiva": "Academia Deportiva",
    "escuela-deportiva": "Escuela Deportiva",
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

# El campo "disciplina deportiva" solo se muestra en el formulario cuando el
# tipo de negocio es una de estas dos categorías.
CATEGORIAS_CON_DEPORTE = {"academia-deportiva", "escuela-deportiva"}

SERVICIOS = [
    "Clases grupales", "Clases particulares", "Nivel inicial", "Nivel intermedio",
    "Nivel avanzado", "Torneos internos / externos", "Preparación física",
    "Evaluación inicial", "Uniforme incluido", "Movilidad / transporte",
    "Campamentos de verano",
]

TURNOS = ["Mañana", "Tarde", "Noche"]

DEPORTES = {
    "Equipo": [
        "Fútbol", "Baloncesto", "Balonmano", "Rugby 7", "Voleibol",
        "Hockey sobre césped", "Flag football", "Lacrosse", "Fútbol americano",
        "Rugby", "Fútbol sala", "Fútbol playa", "Netball", "Korfbal", "Polo",
    ],
    "Raqueta": [
        "Tenis", "Tenis de mesa", "Bádminton", "Squash", "Pádel",
        "Pickleball", "Racquetball", "Frontón",
    ],
    "Bate y pelota": ["Béisbol", "Sóftbol", "Críquet"],
    "Combate": [
        "Judo", "Taekwondo", "Boxeo", "Lucha (libre y grecorromana)", "Esgrima",
        "Artes Marciales Mixtas (MMA)", "Jiu-jitsu brasileño (BJJ)", "Kickboxing",
        "Muay thai", "Karate", "Sumo", "Kung fu", "Capoeira",
    ],
    "Motor": ["Motocross", "Superbike"],
    "Atletismo y fuerza": [
        "Atletismo", "Ciclismo (ruta, pista, MTB y BMX)",
        "Halterofilia (levantamiento de pesas)", "Powerlifting", "CrossFit",
        "Strongman", "Fisicoculturismo",
    ],
    "Acuáticos": [
        "Natación (piscina y aguas abiertas)", "Saltos (clavados)",
        "Natación artística", "Waterpolo", "Surf", "Vela", "Remo",
        "Piragüismo (canotaje)",
    ],
    "Precisión y gimnasia": [
        "Tiro con arco", "Tiro deportivo", "Golf",
        "Gimnasia (artística, rítmica y trampolín)",
    ],
    "Mesa y puntería": [
        "Billar (pool, snooker, carambola)", "Dardos", "Bolos (bowling)", "Bochas",
    ],
    "Mente": ["Ajedrez", "Damas", "Go", "Bridge", "Deportes electrónicos (eSports)"],
    "Aventura y montaña": [
        "Escalada deportiva", "Paracaidismo", "Parapente", "Vuelo sin motor",
        "Salto BASE", "Montañismo / alpinismo de expedición",
    ],
}

# Rangos de precio público ($/$$/$$$) derivados del precio real en soles.
# Deben coincidir exactamente con PRECIO_TIER_* en api/registrar.php.
def precio_tier(soles):
    if soles is None:
        return "$$"
    if soles <= 150:
        return "$"
    if soles <= 350:
        return "$$"
    return "$$$"


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
    lines.append("-- UNE Sports — Datos iniciales (generado automáticamente desde data/*.json")
    lines.append("-- y los catálogos fijos declarados en database/generate_seed.py)")
    lines.append("-- No editar a mano: volver a correr database/generate_seed.py si cambian.")
    lines.append("-- =========================================================================")
    lines.append("")
    lines.append("SET NAMES utf8mb4;")
    lines.append("SET FOREIGN_KEY_CHECKS = 0;")
    lines.append("")

    # --- Categorías (lista fija) ---
    lines.append("-- Categorías de negocio (lista fija)")
    lines.append("INSERT INTO categorias (slug, nombre) VALUES")
    lines.append(",\n".join(f"  ({esc(slug)}, {esc(nombre)})" for slug, nombre in CATEGORIAS.items()) + ";")
    lines.append("")
    cat_id_by_slug = {slug: i + 1 for i, slug in enumerate(CATEGORIAS.keys())}

    # --- Disciplinas deportivas (catálogo fijo agrupado) ---
    lines.append("-- Disciplinas deportivas (catálogo fijo, agrupado)")
    lines.append("INSERT INTO deportes (nombre, grupo, orden) VALUES")
    deporte_rows = []
    deporte_id_by_name = {}
    i = 0
    for grupo, nombres in DEPORTES.items():
        for nombre in nombres:
            i += 1
            deporte_rows.append(f"  ({esc(nombre)}, {esc(grupo)}, {i})")
            deporte_id_by_name[nombre] = i
    lines.append(",\n".join(deporte_rows) + ";")
    lines.append("")

    # --- Servicios (catálogo fijo) ---
    lines.append("-- Servicios (catálogo fijo)")
    lines.append("INSERT INTO servicios (nombre, orden) VALUES")
    lines.append(",\n".join(f"  ({esc(s)}, {i})" for i, s in enumerate(SERVICIOS)) + ";")
    lines.append("")
    serv_id_by_name = {name: i + 1 for i, name in enumerate(SERVICIOS)}

    # --- Catálogo de distritos: se puebla aparte desde ubicaciones_peru.sql ---

    # --- Negocios ---
    lines.append("-- Negocios")
    lines.append(
        "INSERT INTO negocios "
        "(id, slug, nombre, categoria_id, region, provincia, distrito, direccion, telefono, "
        "whatsapp, email, precio_soles, precio, atiende_manana, atiende_tarde, atiende_noche, "
        "descripcion, imagen_principal, contacto_nombre, destacado, verificado, estado, "
        "valoracion_promedio, total_resenas, lat, lng) VALUES"
    )
    neg_rows = []
    for n in negocios:
        precioSoles = n.get("precioSoles")
        turnos = n.get("turnos", [])
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
                num_or_null(precioSoles),
                esc(precio_tier(precioSoles)),
                bool_sql("Mañana" in turnos),
                bool_sql("Tarde" in turnos),
                bool_sql("Noche" in turnos),
                esc(n["descripcion"]),
                esc(n["imagenPrincipal"]),
                esc(n["contacto"]["nombre"]),
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

    # --- negocio_deportes ---
    nd_rows = []
    for n in negocios:
        for d in n.get("deportes", []):
            nd_rows.append(f"  ({n['id']}, {deporte_id_by_name[d]})")
    if nd_rows:
        lines.append("-- Relación negocio <-> disciplinas deportivas")
        lines.append("INSERT INTO negocio_deportes (negocio_id, deporte_id) VALUES")
        lines.append(",\n".join(nd_rows) + ";")
        lines.append("")

    # --- negocio_servicios ---
    ns_rows = []
    for n in negocios:
        for s in n.get("servicios", []):
            ns_rows.append(f"  ({n['id']}, {serv_id_by_name[s]})")
    if ns_rows:
        lines.append("-- Relación negocio <-> servicios")
        lines.append("INSERT INTO negocio_servicios (negocio_id, servicio_id) VALUES")
        lines.append(",\n".join(ns_rows) + ";")
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
