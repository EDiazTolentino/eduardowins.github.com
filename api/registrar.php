<?php
/**
 * UNE Sports — POST /api/registrar.php
 * Recibe el formulario de registrar.html y crea el negocio con
 * estado = "pendiente" (no aparece en el directorio público hasta que un
 * administrador lo revise y lo pase a "publicado" desde phpMyAdmin o un
 * futuro panel de administración).
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

une_require_method('POST');

$input = une_json_input();

$nombre = une_str($input, 'nombre', true);
$tipoNombre = une_str($input, 'tipo', true);
$precio = une_str($input, 'precio', true);
$region = une_str($input, 'region', true);
$provincia = une_str($input, 'provincia', true);
$distrito = une_str($input, 'distrito', true);
$direccion = une_str($input, 'direccion', true);
$telefono = une_str($input, 'telefono', true);
$whatsapp = une_str($input, 'whatsapp', true);
$email = une_str($input, 'email', true);
$horario = une_str($input, 'horario', true);
$contactoNombre = une_str($input, 'contactoNombre', true);
$descripcion = une_str($input, 'descripcion', true);
$servicios = is_array($input['servicios'] ?? null) ? array_slice($input['servicios'], 0, 20) : [];

if (!in_array($precio, ['$', '$$', '$$$'], true)) {
    une_send_json(['error' => 'Rango de precio inválido.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    une_send_json(['error' => 'El correo electrónico no es válido.'], 400);
}

$pdo = une_db();

function une_slugify(string $text): string
{
    $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u'];
    $text = strtr(mb_strtolower($text), $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

$pdo->beginTransaction();
try {
    // Categoría: reutiliza la existente o la crea si es nueva ("Otro", etc.)
    $stmt = $pdo->prepare('SELECT id FROM categorias WHERE nombre = ?');
    $stmt->execute([$tipoNombre]);
    $categoriaId = $stmt->fetchColumn();
    if (!$categoriaId) {
        $slugCat = une_slugify($tipoNombre) ?: ('categoria-' . uniqid());
        $ins = $pdo->prepare('INSERT INTO categorias (slug, nombre) VALUES (?, ?)');
        $ins->execute([$slugCat, $tipoNombre]);
        $categoriaId = (int) $pdo->lastInsertId();
    }

    // Slug único del negocio
    $baseSlug = une_slugify($nombre) ?: ('negocio-' . uniqid());
    $slug = $baseSlug;
    $i = 2;
    $check = $pdo->prepare('SELECT COUNT(*) FROM negocios WHERE slug = ?');
    while (true) {
        $check->execute([$slug]);
        if ((int) $check->fetchColumn() === 0) {
            break;
        }
        $slug = $baseSlug . '-' . $i;
        $i++;
    }

    $insertNegocio = $pdo->prepare(
        'INSERT INTO negocios
         (slug, nombre, categoria_id, region, provincia, distrito, direccion, telefono, whatsapp, email,
          precio, descripcion, contacto_nombre, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pendiente")'
    );
    $insertNegocio->execute([
        $slug, $nombre, $categoriaId, $region, $provincia, $distrito, $direccion,
        $telefono, $whatsapp, $email, $precio, $descripcion, $contactoNombre,
    ]);
    $negocioId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO negocio_horarios (negocio_id, dia, hora, orden) VALUES (?, "Horario de atención", ?, 0)')
        ->execute([$negocioId, $horario]);

    if ($servicios) {
        $findServicio = $pdo->prepare('SELECT id FROM servicios WHERE nombre = ?');
        $createServicio = $pdo->prepare('INSERT INTO servicios (nombre) VALUES (?)');
        $linkServicio = $pdo->prepare('INSERT IGNORE INTO negocio_servicios (negocio_id, servicio_id, orden) VALUES (?, ?, ?)');
        foreach (array_values($servicios) as $orden => $nombreServicio) {
            $nombreServicio = trim((string) $nombreServicio);
            if ($nombreServicio === '' || mb_strlen($nombreServicio) > 150) {
                continue;
            }
            $findServicio->execute([$nombreServicio]);
            $servicioId = $findServicio->fetchColumn();
            if (!$servicioId) {
                $createServicio->execute([$nombreServicio]);
                $servicioId = (int) $pdo->lastInsertId();
            }
            $linkServicio->execute([$negocioId, $servicioId, $orden]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('UNE Sports - error al registrar negocio: ' . $e->getMessage());
    une_send_json(['error' => 'No se pudo registrar el negocio, intenta nuevamente.'], 500);
}

une_send_json(['ok' => true, 'slug' => $slug, 'mensaje' => 'Registro recibido. Será revisado antes de publicarse.'], 201);
