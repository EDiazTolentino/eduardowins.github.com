<?php
/**
 * index.php — Front controller ligero.
 *
 * admin/*.php y api/*.php son archivos reales servidos directamente por
 * Apache (ver .htaccess: si el archivo existe en disco, no pasa por acá).
 * Este router solo resuelve las páginas públicas "bonitas".
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = BaseDatos::obtener();

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$ruta = rtrim($ruta, '/');
if ($ruta === '') {
    $ruta = '/';
}

// Rutas estáticas (sin parámetros)
$rutasEstaticas = [
    '/' => 'pages/home.php',
    '/registrar' => 'pages/registrar.php',
    '/legal-privacidad' => 'pages/legal-privacidad.php',
];

if (isset($rutasEstaticas[$ruta])) {
    require __DIR__ . '/' . $rutasEstaticas[$ruta];
    return;
}

// Ficha pública: /negocio/{slug}
if (preg_match('#^/negocio/([a-z0-9-]+)$#', $ruta, $m)) {
    $slug = $m[1];
    require __DIR__ . '/pages/negocio.php';
    return;
}

// Solicitar corrección o retiro de ficha: /solicitar-retiro/{slug}
if (preg_match('#^/solicitar-retiro/([a-z0-9-]+)$#', $ruta, $m)) {
    $slug = $m[1];
    require __DIR__ . '/pages/solicitar-retiro.php';
    return;
}

http_response_code(404);
$tituloPagina = 'Página no encontrada — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="contenedor seccion-error-404">
  <h1>No encontramos esta página</h1>
  <p>El enlace puede estar roto o la página ya no existe.</p>
  <p><a href="/" class="boton boton--primario">Volver al inicio</a></p>
</section>
<?php
require __DIR__ . '/includes/footer.php';
