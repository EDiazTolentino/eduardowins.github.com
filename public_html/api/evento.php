<?php
/**
 * api/evento.php — Registro de analítica interna (clics de contacto,
 * compartir, etc. desde la ficha pública). No requiere sesión.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tiposPermitidos = ['clic_telefono', 'clic_whatsapp', 'clic_compartir', 'vista_ficha'];

$entrada = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$tipo = $entrada['tipo'] ?? '';
$negocioId = isset($entrada['negocio_id']) ? (int) $entrada['negocio_id'] : null;

if (!in_array($tipo, $tiposPermitidos, true) || !$negocioId) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = BaseDatos::obtener();

registrarEvento($pdo, $tipo, $negocioId);

if (in_array($tipo, ['clic_telefono', 'clic_whatsapp', 'clic_compartir'], true)) {
    $pdo->prepare('UPDATE une_negocios SET clics_contacto = clics_contacto + 1 WHERE id = :id')
        ->execute([':id' => $negocioId]);
}

echo json_encode(['ok' => true]);
