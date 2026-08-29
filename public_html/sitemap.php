<?php
/**
 * sitemap.php — Sitemap XML dinámico (§9). Incluye páginas estáticas,
 * fichas publicadas, artículos publicados y páginas geográficas que
 * tienen al menos una ficha publicada (para no llenar el sitemap con
 * distritos vacíos).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = BaseDatos::obtener();

$urls = [];
$hoy = date('Y-m-d');

foreach (['/', '/buscar', '/registrar', '/sugerir', '/blog', '/servicios', '/nosotros', '/contacto', '/legal-privacidad', '/legal-terminos'] as $ruta) {
    $urls[] = ['loc' => SITE_URL . $ruta, 'lastmod' => $hoy, 'prioridad' => $ruta === '/' ? '1.0' : '0.7'];
}

$stmtNegocios = $pdo->query("SELECT slug, actualizado_en FROM une_negocios WHERE estado = 'publicado'");
foreach ($stmtNegocios->fetchAll() as $n) {
    $urls[] = [
        'loc' => SITE_URL . '/negocio/' . $n['slug'],
        'lastmod' => date('Y-m-d', strtotime($n['actualizado_en'])),
        'prioridad' => '0.8',
    ];
}

$stmtArticulos = $pdo->query('SELECT slug, creado_en FROM une_articulos WHERE publicado = 1');
foreach ($stmtArticulos->fetchAll() as $a) {
    $urls[] = [
        'loc' => SITE_URL . '/blog/' . $a['slug'],
        'lastmod' => date('Y-m-d', strtotime($a['creado_en'])),
        'prioridad' => '0.6',
    ];
}

$stmtCobertura = $pdo->query(
    "SELECT dep.nombre AS departamento, dist.nombre AS distrito, MAX(n.actualizado_en) AS ultima
     FROM une_negocios n
     JOIN une_departamentos dep ON dep.id = n.departamento_id
     LEFT JOIN une_distritos dist ON dist.id = n.distrito_id
     WHERE n.estado = 'publicado'
     GROUP BY dep.id, dist.id"
);
$departamentosConCobertura = [];
foreach ($stmtCobertura->fetchAll() as $fila) {
    $depSlug = generarSlugBase($fila['departamento']);
    if (!isset($departamentosConCobertura[$depSlug])) {
        $departamentosConCobertura[$depSlug] = $fila['ultima'];
        $urls[] = ['loc' => SITE_URL . '/academias/' . $depSlug, 'lastmod' => date('Y-m-d', strtotime($fila['ultima'])), 'prioridad' => '0.6'];
    }
    if ($fila['distrito']) {
        $urls[] = [
            'loc' => SITE_URL . '/academias/' . $depSlug . '/' . generarSlugBase($fila['distrito']),
            'lastmod' => date('Y-m-d', strtotime($fila['ultima'])),
            'prioridad' => '0.6',
        ];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    echo '    <priority>' . $u['prioridad'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
