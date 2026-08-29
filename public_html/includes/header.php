<?php
/**
 * header.php — Cabecera y navegación del sitio público.
 * La página que lo incluye puede definir antes:
 *   $tituloPagina, $metaDescripcion, $canonical, $ogImagen
 */

$tituloPagina = $tituloPagina ?? SITE_NAME . ' — Directorio Nacional de Deporte Formativo';
$metaDescripcion = $metaDescripcion ?? 'Encuentra academias, escuelas y centros de deporte formativo para niños y adolescentes en todo el Perú.';
$canonical = $canonical ?? SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/');
$ogImagen = $ogImagen ?? SITE_URL . '/assets/img/og-default.jpg';

// El logo puede subirse en cualquiera de estos formatos; se usa el
// primero que exista y no esté vacío (por si el .svg dio problemas
// de tipo MIME en el hosting).
$logoArchivo = null;
$logoMime = null;
foreach ([
    'logo.svg' => 'image/svg+xml',
    'logo.webp' => 'image/webp',
    'logo.png' => 'image/png',
    'logo.jpg' => 'image/jpeg',
] as $nombre => $mime) {
    $ruta = RUTA_BASE . '/assets/img/' . $nombre;
    if (is_file($ruta) && filesize($ruta) > 0) {
        $logoArchivo = $nombre;
        $logoMime = $mime;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es-PE">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($tituloPagina) ?></title>
<meta name="description" content="<?= e($metaDescripcion) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($tituloPagina) ?>">
<meta property="og:description" content="<?= e($metaDescripcion) ?>">
<meta property="og:image" content="<?= e($ogImagen) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($tituloPagina) ?>">
<meta name="twitter:description" content="<?= e($metaDescripcion) ?>">
<meta name="twitter:image" content="<?= e($ogImagen) ?>">

<?php if ($logoArchivo): ?>
<link rel="icon" href="/assets/img/<?= e($logoArchivo) ?>" type="<?= e($logoMime) ?>">
<link rel="apple-touch-icon" href="/assets/img/<?= e($logoArchivo) ?>">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/assets/css/estilos.css?v=<?= @filemtime(RUTA_BASE . '/assets/css/estilos.css') ?: '1' ?>">
</head>
<body>
<a href="#contenido" class="salto-enlace">Saltar al contenido</a>

<header class="cabecera">
  <div class="contenedor cabecera__interior">
    <a href="/" class="cabecera__logo" aria-label="<?= e(SITE_NAME) ?> — inicio">
      <?php if ($logoArchivo): ?>
        <img src="/assets/img/<?= e($logoArchivo) ?>" alt="" height="36">
        <span class="cabecera__marca-texto">UNE SPORTS</span>
      <?php else: ?>
        <?php include __DIR__ . '/logo-inline.php'; ?>
      <?php endif; ?>
    </a>

    <button type="button" class="cabecera__menu-toggle" aria-expanded="false" aria-controls="menu-principal" id="menu-toggle">
      <span></span><span></span><span></span>
      <span class="sr-solo">Abrir menú</span>
    </button>

    <nav class="cabecera__nav" id="menu-principal">
      <a href="/">Inicio</a>
      <a href="/buscar">Buscar</a>
      <a href="/registrar">Registrar</a>
      <a href="/blog">Blog</a>
      <a href="/servicios">Servicios</a>
      <a href="/nosotros">Nosotros</a>
      <a href="/contacto">Contacto</a>
    </nav>

    <a href="/registrar" class="boton boton--primario cabecera__cta">Registra tu academia</a>
  </div>
</header>

<main id="contenido">
