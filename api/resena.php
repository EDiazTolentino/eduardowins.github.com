<?php
/**
 * UNE Sports — POST /api/resena.php
 * Body JSON: { slug, autor, valoracion (1-5), comentario }
 * Inserta una reseña real en la base de datos y recalcula el promedio del
 * negocio. Devuelve la reseña creada y el nuevo resumen de valoración.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

une_require_method('POST');

$input = une_json_input();
$slug = une_str($input, 'slug', true);
$autor = une_str($input, 'autor', true);
$comentario = une_str($input, 'comentario', true);
$puntuacion = isset($input['valoracion']) ? (int) $input['valoracion'] : 0;

if ($puntuacion < 1 || $puntuacion > 5) {
    une_send_json(['error' => 'La valoración debe ser un número entre 1 y 5.'], 400);
}
if (mb_strlen($autor) > 150) {
    une_send_json(['error' => 'El nombre es demasiado largo.'], 400);
}
if (mb_strlen($comentario) > 2000) {
    une_send_json(['error' => 'El comentario es demasiado largo.'], 400);
}

$pdo = une_db();

$stmt = $pdo->prepare('SELECT id, valoracion_promedio, total_resenas FROM negocios WHERE slug = ? AND estado = "publicado"');
$stmt->execute([$slug]);
$negocio = $stmt->fetch();

if (!$negocio) {
    une_send_json(['error' => 'No se encontró el negocio indicado.'], 404);
}

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        'INSERT INTO valoraciones (negocio_id, usuario_nombre, puntuacion, comentario) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([$negocio['id'], $autor, $puntuacion, $comentario]);

    $nuevoTotal = (int) $negocio['total_resenas'] + 1;
    $nuevoPromedio = round(
        (((float) $negocio['valoracion_promedio']) * (int) $negocio['total_resenas'] + $puntuacion) / $nuevoTotal,
        1
    );

    $update = $pdo->prepare('UPDATE negocios SET valoracion_promedio = ?, total_resenas = ? WHERE id = ?');
    $update->execute([$nuevoPromedio, $nuevoTotal, $negocio['id']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('UNE Sports - error al guardar reseña: ' . $e->getMessage());
    une_send_json(['error' => 'No se pudo guardar la reseña, intenta nuevamente.'], 500);
}

une_send_json([
    'resena' => [
        'autor' => $autor,
        'avatar' => null,
        'valoracion' => $puntuacion,
        'fecha' => date('Y-m-d'),
        'comentario' => $comentario,
    ],
    'valoracion' => $nuevoPromedio,
    'numResenas' => $nuevoTotal,
]);
