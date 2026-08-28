<?php
/**
 * UNE Sports — GET /api/ubicaciones.php
 * Devuelve el catálogo completo de región / provincia / distrito del Perú
 * (tabla distritos_peru), para poblar los filtros de búsqueda y los
 * selects del formulario de registro, independientemente de qué negocios
 * existan ya en la base.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

une_require_method('GET');

$pdo = une_db();
$rows = $pdo->query('SELECT region, provincia, distrito FROM distritos_peru ORDER BY region, provincia, distrito')->fetchAll();

une_send_json($rows);
