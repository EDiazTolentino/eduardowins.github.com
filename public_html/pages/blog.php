<?php
/**
 * pages/blog.php — Listado del blog (§8).
 */

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 9;
$offset = ($pagina - 1) * $porPagina;

$total = (int) $pdo->query('SELECT COUNT(*) FROM une_articulos WHERE publicado = 1')->fetchColumn();
$totalPaginas = max(1, (int) ceil($total / $porPagina));

$stmt = $pdo->prepare("SELECT titulo, slug, resumen, imagen, categoria, fecha FROM une_articulos WHERE publicado = 1 ORDER BY fecha DESC LIMIT {$porPagina} OFFSET {$offset}");
$stmt->execute();
$articulos = $stmt->fetchAll();

$tituloPagina = 'Blog — ' . SITE_NAME;
$metaDescripcion = 'Artículos sobre deporte formativo, salvaguarda infantil y el rol de los padres en la formación deportiva de sus hijos.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-blog">
  <h1>Blog</h1>
  <p class="texto-ayuda">Artículos sobre deporte formativo para familias peruanas.</p>

  <?php if (!$articulos): ?>
    <p>Todavía no hay artículos publicados.</p>
  <?php else: ?>
    <div class="grid-blog">
      <?php foreach ($articulos as $a): ?>
        <a href="/blog/<?= e($a['slug']) ?>" class="tarjeta-articulo">
          <?php if ($a['imagen']): ?>
            <img src="/uploads/galeria/<?= e($a['imagen']) ?>" alt="<?= e($a['titulo']) ?>" loading="lazy" width="360" height="200">
          <?php endif; ?>
          <?php if ($a['categoria']): ?><span class="etiqueta"><?= e($a['categoria']) ?></span><?php endif; ?>
          <h2><?= e($a['titulo']) ?></h2>
          <?php if ($a['resumen']): ?><p><?= e($a['resumen']) ?></p><?php endif; ?>
          <time datetime="<?= e($a['fecha']) ?>" class="texto-ayuda"><?= e(date('d/m/Y', strtotime($a['fecha']))) ?></time>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
      <nav class="paginacion">
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
          <a href="?pagina=<?= $p ?>" class="<?= $p === $pagina ? 'activo' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
