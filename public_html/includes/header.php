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

<link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/logo.svg">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/assets/css/estilos.css">
</head>
<body>
<a href="#contenido" class="salto-enlace">Saltar al contenido</a>

<header class="cabecera">
  <div class="contenedor cabecera__interior">
    <a href="/" class="cabecera__logo" aria-label="<?= e(SITE_NAME) ?> — inicio">
      <?php if (is_file(RUTA_BASE . '/assets/img/logo.svg') && filesize(RUTA_BASE . '/assets/img/logo.svg') > 0): ?>
        <img src="/assets/img/logo.svg" alt="<?= e(SITE_NAME) ?>" height="36">
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
      <a href="/registrar">Registrar academia</a>
    </nav>

    <a href="/registrar" class="boton boton--primario cabecera__cta">Registra tu academia</a>
  </div>
</header>

<main id="contenido">
