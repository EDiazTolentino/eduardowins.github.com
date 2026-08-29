<?php
/**
 * api/buscar.php — Resultados para el mapa del buscador (§8), en JSON.
 * Acepta los mismos parámetros de filtro que pages/buscar.php.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = BaseDatos::obtener();

$dep = (int) ($_GET['dep'] ?? 0) ?: null;
$prov = (int) ($_GET['prov'] ?? 0) ?: null;
$dist = (int) ($_GET['dist'] ?? 0) ?: null;
$tipo = in_array($_GET['tipo'] ?? '', ['formativo', 'servicio'], true) ? $_GET['tipo'] : null;
$categoria = (int) ($_GET['categoria'] ?? 0) ?: null;
$deporte = (int) ($_GET['deporte'] ?? 0) ?: null;
$etapa = (int) ($_GET['etapa'] ?? 0) ?: null;
$turno = in_array($_GET['turno'] ?? '', ['mañana', 'tarde', 'noche'], true) ? $_GET['turno'] : null;
$precio = (int) ($_GET['precio'] ?? 0) ?: null;
$verificado = isset($_GET['verificado']);
$localPropio = isset($_GET['local_propio']);
$pruebaGratis = isset($_GET['prueba_gratis']);
$q = trim((string) ($_GET['q'] ?? ''));

$condiciones = ["n.estado = 'publicado'", 'n.latitud IS NOT NULL', 'n.longitud IS NOT NULL'];
$params = [];

if ($dep) { $condiciones[] = 'n.departamento_id = :dep'; $params[':dep'] = $dep; }
if ($prov) { $condiciones[] = 'n.provincia_id = :prov'; $params[':prov'] = $prov; }
if ($dist) { $condiciones[] = 'n.distrito_id = :dist'; $params[':dist'] = $dist; }
if ($tipo) { $condiciones[] = 'n.tipo_registro = :tipo'; $params[':tipo'] = $tipo; }
if ($categoria) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_negocio_categorias nc WHERE nc.negocio_id = n.id AND nc.categoria_id = :categoria)';
    $params[':categoria'] = $categoria;
}
if ($deporte) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_negocio_deportes nd WHERE nd.negocio_id = n.id AND nd.deporte_id = :deporte)';
    $params[':deporte'] = $deporte;
}
if ($etapa) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_negocio_etapas ne WHERE ne.negocio_id = n.id AND ne.etapa_id = :etapa)';
    $params[':etapa'] = $etapa;
}
if ($turno) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_horarios h WHERE h.negocio_id = n.id AND h.turno = :turno)';
    $params[':turno'] = $turno;
}
if ($precio) { $condiciones[] = 'n.rango_precio = :precio'; $params[':precio'] = $precio; }
if ($verificado) { $condiciones[] = 'n.verificado = 1'; }
if ($localPropio) { $condiciones[] = 'n.local_propio = 1'; }
if ($pruebaGratis) { $condiciones[] = 'n.clase_prueba_gratis = 1'; }
if ($q !== '') {
    $condiciones[] = 'n.nombre_comercial LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$stmt = $pdo->prepare(
    'SELECT n.slug, n.nombre_comercial, n.verificado, n.latitud, n.longitud
     FROM une_negocios n
     WHERE ' . implode(' AND ', $condiciones) . '
     LIMIT 500'
);
$stmt->execute($params);

$marcadores = array_map(static function (array $n): array {
    return [
        'slug' => $n['slug'],
        'nombre' => $n['nombre_comercial'],
        'verificado' => (bool) $n['verificado'],
        'lat' => (float) $n['latitud'],
        'lng' => (float) $n['longitud'],
    ];
}, $stmt->fetchAll());

echo json_encode($marcadores, JSON_UNESCAPED_UNICODE);
