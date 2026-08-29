<?php
/**
 * pages/academias.php — Páginas de aterrizaje geográficas (Fase 2, §9):
 * /academias/{departamento} y /academias/{departamento}/{distrito}.
 * index.php pasa $depSlug y, si aplica, $distSlug.
 *
 * No se agregó una columna `slug` a une_departamentos/une_distritos para
 * no forzar una migración sobre un sitio ya desplegado: el slug se
 * calcula al vuelo con generarSlugBase() y se compara en PHP. A esta
 * escala (26 departamentos, unos pocos cientos de distritos por
 * departamento) el costo es insignificante.
 */

$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1')->fetchAll();
$departamento = null;
foreach ($departamentos as $d) {
    if (generarSlugBase($d['nombre']) === $depSlug) {
        $departamento = $d;
        break;
    }
}
if (!$departamento) {
    http_response_code(404);
    $tituloPagina = 'Página no encontrada — ' . SITE_NAME;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="contenedor seccion-error-404"><h1>No encontramos este departamento</h1></section>';
    require __DIR__ . '/../includes/footer.php';
    return;
}

$distrito = null;
if (isset($distSlug)) {
    $stmtDistritos = $pdo->prepare(
        'SELECT d.id, d.nombre FROM une_distritos d
         JOIN une_provincias p ON p.id = d.provincia_id
         WHERE p.departamento_id = :dep AND d.activo = 1'
    );
    $stmtDistritos->execute([':dep' => $departamento['id']]);
    foreach ($stmtDistritos->fetchAll() as $d) {
        if (generarSlugBase($d['nombre']) === $distSlug) {
            $distrito = $d;
            break;
        }
    }
    if (!$distrito) {
        http_response_code(404);
        $tituloPagina = 'Página no encontrada — ' . SITE_NAME;
        require __DIR__ . '/../includes/header.php';
        echo '<section class="contenedor seccion-error-404"><h1>No encontramos este distrito</h1></section>';
        require __DIR__ . '/../includes/footer.php';
        return;
    }
}

$condiciones = ["n.estado = 'publicado'", 'n.departamento_id = :dep'];
$params = [':dep' => $departamento['id']];
if ($distrito) {
    $condiciones[] = 'n.distrito_id = :dist';
    $params[':dist'] = $distrito['id'];
}

$stmt = $pdo->prepare(
    'SELECT n.slug, n.nombre_comercial, n.descripcion, n.verificado, n.rango_precio,
            dist.nombre AS distrito
     FROM une_negocios n
     LEFT JOIN une_distritos dist ON dist.id = n.distrito_id
     WHERE ' . implode(' AND ', $condiciones) . '
     ORDER BY n.verificado DESC, n.completitud DESC, n.nombre_comercial ASC
     LIMIT 24'
);
$stmt->execute($params);
$resultados = $stmt->fetchAll();

$lugar = $distrito ? $distrito['nombre'] . ', ' . $departamento['nombre'] : $departamento['nombre'];
$tituloPagina = "Academias de deporte formativo en {$lugar} | " . SITE_NAME;
$metaDescripcion = "Encuentra academias, escuelas y centros de deporte formativo para niños y adolescentes en {$lugar}, Perú.";
require __DIR__ . '/../includes/header.php';
?>
<nav class="migas-pan contenedor" aria-label="Ruta de navegación">
  <a href="/">Inicio</a> ›
  <a href="/academias/<?= e(generarSlugBase($departamento['nombre'])) ?>"><?= e($departamento['nombre']) ?></a>
  <?php if ($distrito): ?> › <span><?= e($distrito['nombre']) ?></span><?php endif; ?>
</nav>

<section class="contenedor seccion-buscar">
  <h1>Academias de deporte formativo en <?= e($lugar) ?></h1>
  <p class="texto-ayuda">
    <?= count($resultados) ?> academia(s) y centro(s) registrados en <?= e($lugar) ?>.
    <a href="/buscar?dep=<?= (int) $departamento['id'] ?><?= $distrito ? '&dist=' . (int) $distrito['id'] : '' ?>">Refinar la búsqueda</a>
  </p>

  <?php if (!$resultados): ?>
    <div class="alerta alerta--info">
      Todavía no tenemos academias registradas en <?= e($lugar) ?>.
      ¿Conoces alguna? <a href="/sugerir">Sugiérela aquí</a> o
      <a href="/registrar">regístrala gratis</a> si es tuya.
    </div>
  <?php else: ?>
    <div class="tarjetas-negocios">
      <?php foreach ($resultados as $n): ?>
        <a href="/negocio/<?= e($n['slug']) ?>" class="tarjeta-negocio">
          <?php if ($n['verificado']): ?><span class="insignia insignia--verificada">Verificada</span>
          <?php else: ?><span class="insignia insignia--no-verificada">No verificada</span><?php endif; ?>
          <h3><?= e($n['nombre_comercial']) ?></h3>
          <p class="tarjeta-negocio__ubicacion"><?= e($n['distrito'] ?? '') ?></p>
          <?php if ($n['descripcion']): ?><p class="tarjeta-negocio__descripcion"><?= e(mb_substr($n['descripcion'], 0, 100)) ?>&hellip;</p><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
