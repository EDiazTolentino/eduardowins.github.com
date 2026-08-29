<?php
/**
 * api/ubigeo.php — Autocompletado de distrito en un solo gesto (§7A).
 *
 * GET ?q=texto  -> hasta 10 distritos que calzan, con departamento y
 *                  provincia ya resueltos, formato "Distrito — Provincia — Departamento".
 * GET ?departamento_id=N -> lista de provincias de ese departamento (uso en backoffice).
 * GET ?provincia_id=N    -> lista de distritos de esa provincia (uso en backoffice).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = BaseDatos::obtener();

if (isset($_GET['q'])) {
    $q = trim((string) $_GET['q']);
    if (mb_strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT d.id AS distrito_id, d.nombre AS distrito,
                p.id AS provincia_id, p.nombre AS provincia,
                dep.id AS departamento_id, dep.nombre AS departamento
         FROM une_distritos d
         JOIN une_provincias p ON p.id = d.provincia_id
         JOIN une_departamentos dep ON dep.id = p.departamento_id
         WHERE d.activo = 1 AND d.nombre LIKE :q
         ORDER BY d.nombre ASC
         LIMIT 10'
    );
    $stmt->execute([':q' => $q . '%']);
    $filas = $stmt->fetchAll();

    $resultado = array_map(static function (array $f): array {
        return [
            'departamento_id' => (int) $f['departamento_id'],
            'provincia_id' => (int) $f['provincia_id'],
            'distrito_id' => (int) $f['distrito_id'],
            'etiqueta' => "{$f['distrito']} — {$f['provincia']} — {$f['departamento']}",
        ];
    }, $filas);

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['departamento_id'])) {
    $depId = (int) $_GET['departamento_id'];
    $stmt = $pdo->prepare('SELECT id, nombre FROM une_provincias WHERE departamento_id = :dep AND activo = 1 ORDER BY nombre');
    $stmt->execute([':dep' => $depId]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['provincia_id'])) {
    $provId = (int) $_GET['provincia_id'];
    $stmt = $pdo->prepare('SELECT id, nombre FROM une_distritos WHERE provincia_id = :prov AND activo = 1 ORDER BY nombre');
    $stmt->execute([':prov' => $provId]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['departamentos'])) {
    $stmt = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo');
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Parámetro no reconocido.']);
