<?php
/**
 * UNE Sports — POST /api/registrar.php
 * Recibe el formulario de registrar.html y crea el negocio con
 * estado = "pendiente" (no aparece en el directorio público hasta que un
 * administrador lo revise y lo pase a "publicado" desde phpMyAdmin o un
 * futuro panel de administración).
 *
 * precioSoles es el monto real que ingresa el dueño: se guarda pero NUNCA
 * se expone por api/negocios.php. Aquí mismo se deriva un rango público
 * ($/$$/$$$) a partir de PRECIO_TIER_BAJO / PRECIO_TIER_ALTO — deben
 * coincidir con precio_tier() en database/generate_seed.py.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

une_require_method('POST');

const PRECIO_TIER_BAJO = 150;   // <= este monto => "$"
const PRECIO_TIER_ALTO = 350;   // <= este monto => "$$", más => "$$$"

// Categorías que muestran el campo "disciplina deportiva" en el formulario.
const CATEGORIAS_CON_DEPORTE = ['Academia Deportiva', 'Escuela Deportiva'];
const MAX_DEPORTES = 5;

$input = une_json_input();

$nombre = une_str($input, 'nombre', true);
$tipoNombre = une_str($input, 'tipo', true);
$region = une_str($input, 'region', true);
$provincia = une_str($input, 'provincia', true);
$distrito = une_str($input, 'distrito', true);
$direccion = une_str($input, 'direccion', true);
$telefono = une_str($input, 'telefono', true);
$whatsapp = une_str($input, 'whatsapp', true);
$email = une_str($input, 'email', true);
$contactoNombre = une_str($input, 'contactoNombre', true);
$descripcion = une_str($input, 'descripcion', true);
$servicios = is_array($input['servicios'] ?? null) ? $input['servicios'] : [];
$turnos = is_array($input['turnos'] ?? null) ? $input['turnos'] : [];
$deportes = is_array($input['deportes'] ?? null) ? $input['deportes'] : [];
$precioSoles = isset($input['precioSoles']) ? (float) $input['precioSoles'] : null;

if ($precioSoles === null || $precioSoles <= 0) {
    une_send_json(['error' => 'Ingresa el precio en soles (no se mostrará al público).'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    une_send_json(['error' => 'El correo electrónico no es válido.'], 400);
}
if (!$turnos) {
    une_send_json(['error' => 'Selecciona al menos un turno de atención (mañana, tarde o noche).'], 400);
}
$esCategoriaConDeporte = in_array($tipoNombre, CATEGORIAS_CON_DEPORTE, true);
if ($esCategoriaConDeporte && !$deportes) {
    une_send_json(['error' => 'Selecciona al menos una disciplina deportiva.'], 400);
}
if (count($deportes) > MAX_DEPORTES) {
    une_send_json(['error' => 'Puedes elegir como máximo ' . MAX_DEPORTES . ' disciplinas.'], 400);
}

$precioPublico = $precioSoles <= PRECIO_TIER_BAJO ? '$' : ($precioSoles <= PRECIO_TIER_ALTO ? '$$' : '$$$');

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
    // Categoría: reutiliza la existente o la crea si es nueva (no debería
    // pasar en uso normal, ya que el formulario solo ofrece las 14 fijas).
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
          precio_soles, precio, atiende_manana, atiende_tarde, atiende_noche,
          descripcion, contacto_nombre, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pendiente")'
    );
    $insertNegocio->execute([
        $slug, $nombre, $categoriaId, $region, $provincia, $distrito, $direccion,
        $telefono, $whatsapp, $email, $precioSoles, $precioPublico,
        in_array('Mañana', $turnos, true) ? 1 : 0,
        in_array('Tarde', $turnos, true) ? 1 : 0,
        in_array('Noche', $turnos, true) ? 1 : 0,
        $descripcion, $contactoNombre,
    ]);
    $negocioId = (int) $pdo->lastInsertId();

    if ($esCategoriaConDeporte && $deportes) {
        $findDeporte = $pdo->prepare('SELECT id FROM deportes WHERE nombre = ?');
        $linkDeporte = $pdo->prepare('INSERT IGNORE INTO negocio_deportes (negocio_id, deporte_id) VALUES (?, ?)');
        foreach (array_slice($deportes, 0, MAX_DEPORTES) as $nombreDeporte) {
            $findDeporte->execute([trim((string) $nombreDeporte)]);
            $deporteId = $findDeporte->fetchColumn();
            if ($deporteId) {
                $linkDeporte->execute([$negocioId, $deporteId]);
            }
        }
    }

    if ($servicios) {
        // Los servicios son un catálogo cerrado (11 fijos): solo se busca,
        // nunca se crea uno nuevo — evita que llegue texto arbitrario.
        $findServicio = $pdo->prepare('SELECT id FROM servicios WHERE nombre = ?');
        $linkServicio = $pdo->prepare('INSERT IGNORE INTO negocio_servicios (negocio_id, servicio_id) VALUES (?, ?)');
        foreach ($servicios as $nombreServicio) {
            $findServicio->execute([trim((string) $nombreServicio)]);
            $servicioId = $findServicio->fetchColumn();
            if ($servicioId) {
                $linkServicio->execute([$negocioId, $servicioId]);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('UNE Sports - error al registrar negocio: ' . $e->getMessage());
    une_send_json(['error' => 'No se pudo registrar el negocio, intenta nuevamente.'], 500);
}

une_send_json(['ok' => true, 'slug' => $slug, 'mensaje' => 'Registro recibido. Será revisado antes de publicarse.'], 201);
