<?php
/**
 * UNE Sports — GET /api/negocios.php
 * Devuelve todos los negocios publicados con la misma forma que antes tenía
 * data/negocios.json, para que buscar.js y negocio.js no necesiten cambios
 * de lógica: solo se cambió la URL del fetch.
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
     WHERE ns.negocio_id IN ($placeholders) ORDER BY ns.negocio_id, ns.orden",
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

$horarios = une_fetch_grouped(
    $pdo,
    "SELECT negocio_id, dia, hora FROM negocio_horarios
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
        'valoracion' => (float) $n['valoracion_promedio'],
        'numResenas' => (int) $n['total_resenas'],
        'destacado' => (bool) $n['destacado'],
        'verificado' => (bool) $n['verificado'],
        'lat' => $n['lat'] !== null ? (float) $n['lat'] : null,
        'lng' => $n['lng'] !== null ? (float) $n['lng'] : null,
        'descripcion' => $n['descripcion'],
        'servicios' => array_map(fn($s) => $s['nombre'], $servicios[$id] ?? []),
        'horario' => array_map(fn($h) => ['dia' => $h['dia'], 'hora' => $h['hora']], $horarios[$id] ?? []),
        'contacto' => [
            'nombre' => $n['contacto_nombre'],
            'cargo' => $n['contacto_cargo'],
            'foto' => $n['contacto_foto'],
        ],
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
