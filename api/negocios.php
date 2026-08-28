<?php
/**
 * UNE Sports — GET /api/negocios.php
 * Devuelve todos los negocios publicados. A propósito NO incluye
 * precio_soles ni contacto_nombre: son datos privados/internos que el
 * formulario de registro sí guarda, pero que este endpoint público nunca
 * expone (ver comentario en database/schema.sql sobre negocios).
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

une_require_method('GET');

$pdo = une_db();

$negocios = $pdo->query(
    "SELECT n.*, c.slug AS tipo, c.nombre AS tipoLabel
     FROM negocios n
     JOIN categorias c ON c.id = n.categoria_id
     WHERE n.estado = 'publicado'
     ORDER BY n.destacado DESC, n.valoracion_promedio DESC, n.id ASC"
)->fetchAll();

if (!$negocios) {
    une_send_json([]);
}

$ids = array_column($negocios, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));

function une_fetch_grouped(PDO $pdo, string $sql, array $ids, string $groupKey): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $grouped[$row[$groupKey]][] = $row;
    }
    return $grouped;
}

$servicios = une_fetch_grouped(
    $pdo,
    "SELECT ns.negocio_id, s.nombre
     FROM negocio_servicios ns JOIN servicios s ON s.id = ns.servicio_id
     WHERE ns.negocio_id IN ($placeholders) ORDER BY ns.negocio_id, s.orden",
    $ids,
    'negocio_id'
);

$deportes = une_fetch_grouped(
    $pdo,
    "SELECT nd.negocio_id, d.nombre
     FROM negocio_deportes nd JOIN deportes d ON d.id = nd.deporte_id
     WHERE nd.negocio_id IN ($placeholders) ORDER BY nd.negocio_id, d.orden",
    $ids,
    'negocio_id'
);

$imagenes = une_fetch_grouped(
    $pdo,
    "SELECT negocio_id, url FROM negocio_imagenes
     WHERE negocio_id IN ($placeholders) ORDER BY negocio_id, orden",
    $ids,
    'negocio_id'
);

$resenas = une_fetch_grouped(
    $pdo,
    "SELECT negocio_id, usuario_nombre, usuario_avatar, puntuacion, comentario, creado_en
     FROM valoraciones WHERE negocio_id IN ($placeholders) ORDER BY negocio_id, creado_en DESC",
    $ids,
    'negocio_id'
);

$out = [];
foreach ($negocios as $n) {
    $id = (int) $n['id'];
    $turnos = [];
    if ($n['atiende_manana']) $turnos[] = 'Mañana';
    if ($n['atiende_tarde']) $turnos[] = 'Tarde';
    if ($n['atiende_noche']) $turnos[] = 'Noche';

    $out[] = [
        'id' => $id,
        'slug' => $n['slug'],
        'nombre' => $n['nombre'],
        'tipo' => $n['tipo'],
        'tipoLabel' => $n['tipoLabel'],
        'region' => $n['region'],
        'provincia' => $n['provincia'],
        'distrito' => $n['distrito'],
        'direccion' => $n['direccion'],
        'telefono' => $n['telefono'],
        'whatsapp' => $n['whatsapp'],
        'email' => $n['email'],
        'precio' => $n['precio'],
        'turnos' => $turnos,
        'valoracion' => (float) $n['valoracion_promedio'],
        'numResenas' => (int) $n['total_resenas'],
        'destacado' => (bool) $n['destacado'],
        'verificado' => (bool) $n['verificado'],
        'lat' => $n['lat'] !== null ? (float) $n['lat'] : null,
        'lng' => $n['lng'] !== null ? (float) $n['lng'] : null,
        'descripcion' => $n['descripcion'],
        'servicios' => array_map(fn($s) => $s['nombre'], $servicios[$id] ?? []),
        'deportes' => array_map(fn($d) => $d['nombre'], $deportes[$id] ?? []),
        'imagenPrincipal' => $n['imagen_principal'],
        'galeria' => array_map(fn($i) => $i['url'], $imagenes[$id] ?? []),
        'resenas' => array_map(fn($r) => [
            'autor' => $r['usuario_nombre'],
            'avatar' => $r['usuario_avatar'],
            'valoracion' => (int) $r['puntuacion'],
            'fecha' => substr($r['creado_en'], 0, 10),
            'comentario' => $r['comentario'],
        ], $resenas[$id] ?? []),
    ];
}

une_send_json($out);
