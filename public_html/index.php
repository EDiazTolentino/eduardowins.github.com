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
    '/legal-terminos' => 'pages/legal-terminos.php',
    '/buscar' => 'pages/buscar.php',
    '/sugerir' => 'pages/sugerir.php',
    '/blog' => 'pages/blog.php',
    '/servicios' => 'pages/servicios.php',
    '/nosotros' => 'pages/nosotros.php',
    '/contacto' => 'pages/contacto.php',
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

// Reclamar ficha: /reclamar/{slug}
if (preg_match('#^/reclamar/([a-z0-9-]+)$#', $ruta, $m)) {
    $slug = $m[1];
    require __DIR__ . '/pages/reclamar.php';
    return;
}

// Edición vía token, sin login: /editar/{token}
// (case-insensitive: los tokens generados en PHP son minúsculas —
// bin2hex — pero los de la semilla SQL usan HEX(), que es mayúsculas)
if (preg_match('#^/editar/([a-fA-F0-9]{20,64})$#', $ruta, $m)) {
    $token = $m[1];
    require __DIR__ . '/pages/editar.php';
    return;
}

// Artículo de blog: /blog/{slug}
if (preg_match('#^/blog/([a-z0-9-]+)$#', $ruta, $m)) {
    $slug = $m[1];
    require __DIR__ . '/pages/articulo.php';
    return;
}

// Páginas geográficas: /academias/{departamento} o /academias/{departamento}/{distrito}
if (preg_match('#^/academias/([a-z0-9-]+)(?:/([a-z0-9-]+))?$#', $ruta, $m)) {
    $depSlug = $m[1];
    if (isset($m[2]) && $m[2] !== '') {
        $distSlug = $m[2];
    }
    require __DIR__ . '/pages/academias.php';
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
