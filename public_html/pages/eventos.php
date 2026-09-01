<?php
/**
 * pages/eventos.php — Cartelera de eventos deportivos nacionales e
 * internacionales, difundidos por UNE Sports (no organizados por
 * UNE Sports). Filtro por ámbito y, en nacionales, por región.
 */

$ambito = in_array($_GET['ambito'] ?? '', ['nacional', 'internacional'], true) ? $_GET['ambito'] : '';
$departamentoId = (int) ($_GET['departamento_id'] ?? 0);

$condiciones = ["e.publicado = 1", "(e.fecha_evento IS NULL OR e.fecha_evento >= CURDATE())"];
$params = [];
if ($ambito) {
    $condiciones[] = 'e.ambito = :ambito';
    $params[':ambito'] = $ambito;
}
if ($ambito === 'nacional' && $departamentoId) {
    $condiciones[] = 'e.departamento_id = :dep';
    $params[':dep'] = $departamentoId;
}

$stmt = $pdo->prepare(
    'SELECT e.id, e.ambito, e.titulo, e.flyer, e.nota, e.enlace_organizador, e.fecha_evento,
            dep.nombre AS departamento
     FROM une_eventos_deportivos e
     LEFT JOIN une_departamentos dep ON dep.id = e.departamento_id
     WHERE ' . implode(' AND ', $condiciones) . '
     ORDER BY e.fecha_evento IS NULL, e.fecha_evento ASC'
);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo')->fetchAll();

$tituloPagina = 'Eventos deportivos — ' . SITE_NAME;
$metaDescripcion = 'Cartelera de eventos deportivos nacionales e internacionales difundidos por ' . SITE_NAME . '.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-eventos">
  <h1>Eventos deportivos</h1>
  <p class="texto-ayuda">Difundimos información de eventos organizados por terceros (federaciones, ligas, otras instituciones). UNE Sports no organiza estos eventos; para inscripciones y detalles, usa el enlace del organizador.</p>

  <form method="get" class="formulario-filtros">
    <select name="ambito" onchange="this.form.submit()">
      <option value="">Todos los eventos</option>
      <option value="nacional" <?= $ambito === 'nacional' ? 'selected' : '' ?>>Nacionales</option>
      <option value="internacional" <?= $ambito === 'internacional' ? 'selected' : '' ?>>Internacionales</option>
    </select>
    <?php if ($ambito === 'nacional'): ?>
      <select name="departamento_id" onchange="this.form.submit()">
        <option value="">Todas las regiones</option>
        <?php foreach ($departamentos as $dep): ?>
          <option value="<?= (int) $dep['id'] ?>" <?= $departamentoId === (int) $dep['id'] ? 'selected' : '' ?>><?= e($dep['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <noscript><button type="submit" class="boton boton--secundario">Filtrar</button></noscript>
  </form>

  <?php if (!$eventos): ?>
    <p class="texto-ayuda">No hay eventos próximos con este filtro por ahora.</p>
  <?php else: ?>
    <div class="grid-eventos">
      <?php foreach ($eventos as $ev): ?>
        <article class="tarjeta-evento">
          <?php if ($ev['flyer']): ?>
            <img src="/uploads/galeria/<?= e($ev['flyer']) ?>" alt="<?= e($ev['titulo']) ?>" loading="lazy" width="360" height="240">
          <?php endif; ?>
          <div class="tarjeta-evento__cuerpo">
            <span class="etiqueta"><?= $ev['ambito'] === 'nacional' ? 'Nacional' : 'Internacional' ?><?= $ev['departamento'] ? ' · ' . e($ev['departamento']) : '' ?></span>
            <h2><?= e($ev['titulo']) ?></h2>
            <?php if ($ev['fecha_evento']): ?>
              <time datetime="<?= e($ev['fecha_evento']) ?>" class="texto-ayuda"><?= e(date('d/m/Y', strtotime($ev['fecha_evento']))) ?></time>
            <?php endif; ?>
            <?php if ($ev['nota']): ?><p><?= nl2br(e($ev['nota'])) ?></p><?php endif; ?>
            <?php if ($ev['enlace_organizador']): ?>
              <a href="<?= e($ev['enlace_organizador']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="boton boton--secundario">Más información (organizador)</a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
