<?php
/**
 * pages/articulo.php — Ficha de artículo del blog. $slug llega desde
 * index.php. `contenido` se guarda en HTML por el propio equipo editorial
 * (autores internos autenticados, ver admin/articulos.php) y se renderiza
 * sin escapar; nunca proviene de un formulario público.
 */

$stmt = $pdo->prepare('SELECT * FROM une_articulos WHERE slug = :slug AND publicado = 1');
$stmt->execute([':slug' => $slug]);
$articulo = $stmt->fetch();

if (!$articulo) {
    http_response_code(404);
    $tituloPagina = 'Artículo no encontrado — ' . SITE_NAME;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="contenedor seccion-error-404"><h1>No encontramos este artículo</h1></section>';
    require __DIR__ . '/../includes/footer.php';
    return;
}

$relacionados = $pdo->prepare(
    'SELECT titulo, slug FROM une_articulos WHERE publicado = 1 AND id != :id ORDER BY fecha DESC LIMIT 3'
);
$relacionados->execute([':id' => $articulo['id']]);
$relacionados = $relacionados->fetchAll();

$datosEstructurados = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $articulo['titulo'],
    'description' => $articulo['resumen'],
    'datePublished' => $articulo['fecha'],
    'author' => $articulo['autor']
        ? ['@type' => 'Person', 'name' => $articulo['autor']]
        : ['@type' => 'Organization', 'name' => SITE_NAME],
    'publisher' => ['@type' => 'Organization', 'name' => SITE_NAME],
], static fn ($v) => $v !== null);

$tituloPagina = ($articulo['meta_titulo'] ?: $articulo['titulo']) . ' — ' . SITE_NAME;
$metaDescripcion = $articulo['meta_descripcion'] ?: $articulo['resumen'];
require __DIR__ . '/../includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($datosEstructurados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<nav class="migas-pan contenedor" aria-label="Ruta de navegación">
  <a href="/">Inicio</a> › <a href="/blog">Blog</a> › <span><?= e($articulo['titulo']) ?></span>
</nav>

<article class="contenedor seccion-angosta articulo">
  <?php if ($articulo['categoria']): ?><span class="etiqueta"><?= e($articulo['categoria']) ?></span><?php endif; ?>
  <h1><?= e($articulo['titulo']) ?></h1>
  <p class="texto-ayuda articulo__meta">
    <?php if ($articulo['autor']): ?>Por <strong><?= e($articulo['autor']) ?></strong> · <?php endif; ?>
    <time datetime="<?= e($articulo['fecha']) ?>"><?= e(date('d/m/Y', strtotime($articulo['fecha']))) ?></time>
  </p>
  <?php if ($articulo['imagen']): ?>
    <img src="/uploads/galeria/<?= e($articulo['imagen']) ?>" alt="<?= e($articulo['titulo']) ?>" loading="lazy" width="720" height="400">
  <?php endif; ?>
  <div class="articulo__contenido"><?= $articulo['contenido'] ?></div>
</article>

<?php if ($relacionados): ?>
<section class="contenedor seccion-angosta">
  <h2>Más artículos</h2>
  <ul class="lista-relacionadas">
    <?php foreach ($relacionados as $r): ?><li><a href="/blog/<?= e($r['slug']) ?>"><?= e($r['titulo']) ?></a></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php
require __DIR__ . '/../includes/footer.php';
