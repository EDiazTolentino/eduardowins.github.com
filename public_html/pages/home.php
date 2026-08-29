<?php
/**
 * pages/home.php — Inicio. Incluye el buscador prominente y el grid de
 * regiones con más registros de la Fase 2 (§8).
 */

$stmtContador = $pdo->query(
    "SELECT COUNT(*) AS total, COUNT(DISTINCT distrito_id) AS distritos
     FROM une_negocios WHERE estado = 'publicado'"
);
$contador = $stmtContador->fetch();

$stmtDestacadas = $pdo->query(
    "SELECT n.slug, n.nombre_comercial, n.descripcion, n.verificado,
            dist.nombre AS distrito, dep.nombre AS departamento
     FROM une_negocios n
     LEFT JOIN une_distritos dist ON dist.id = n.distrito_id
     LEFT JOIN une_departamentos dep ON dep.id = n.departamento_id
     WHERE n.estado = 'publicado'
     ORDER BY n.verificado DESC, n.completitud DESC
     LIMIT 6"
);
$destacadas = $stmtDestacadas->fetchAll();

$stmtDeportesInicio = $pdo->query('SELECT id, nombre, icono FROM une_deportes ORDER BY orden LIMIT 8');
$deportesInicio = $stmtDeportesInicio->fetchAll();

$stmtRegiones = $pdo->query(
    "SELECT dep.nombre AS departamento, COUNT(*) AS total
     FROM une_negocios n JOIN une_departamentos dep ON dep.id = n.departamento_id
     WHERE n.estado = 'publicado' AND dep.id != " . (int) DEPARTAMENTO_SIN_DEFINIR_ID . '
     GROUP BY dep.id ORDER BY total DESC LIMIT 8'
);
$regiones = $stmtRegiones->fetchAll();

$tituloPagina = SITE_NAME . ' — Directorio Nacional de Deporte Formativo';
require __DIR__ . '/../includes/header.php';
?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => SITE_NAME,
    'url' => SITE_URL,
    'description' => 'Directorio nacional de academias, escuelas y centros de deporte formativo para niños y adolescentes en el Perú.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<section class="hero">
  <div class="contenedor hero__interior">
    <h1>Encuentra la academia deportiva ideal para tu hijo o hija</h1>
    <p class="hero__subtitulo">El directorio nacional de academias, escuelas y centros de deporte formativo del Perú, para niños y adolescentes de 4 a 18 años.</p>

    <form method="get" action="/buscar" class="formulario-buscador-hero">
      <select name="deporte" aria-label="Deporte">
        <option value="">Cualquier deporte</option>
        <?php foreach ($deportesInicio as $d): ?>
          <option value="<?= (int) $d['id'] ?>"><?= e($d['icono']) ?> <?= e($d['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" placeholder="Nombre o distrito" aria-label="Nombre o distrito">
      <button type="submit" class="boton boton--primario">Buscar</button>
    </form>

    <p class="hero__contador">
      <strong><?= (int) $contador['total'] ?></strong> academias registradas en
      <strong><?= (int) $contador['distritos'] ?></strong> distritos del Perú
    </p>
    <a href="/registrar" class="boton boton--secundario">Registra tu academia gratis</a>
  </div>
</section>

<?php if ($deportesInicio): ?>
<section class="contenedor seccion-deportes-rapidos">
  <h2>Busca por deporte</h2>
  <div class="accesos-deportes">
    <?php foreach ($deportesInicio as $d): ?>
      <a href="/buscar?deporte=<?= (int) $d['id'] ?>" class="acceso-deporte">
        <span class="acceso-deporte__icono"><?= e($d['icono']) ?></span>
        <span><?= e($d['nombre']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="contenedor seccion-como-funciona">
  <h2>¿Cómo funciona?</h2>
  <div class="pasos">
    <div class="paso">
      <span class="paso__numero">1</span>
      <h3>Busca</h3>
      <p>Filtra por deporte, ubicación y edad de tu hijo o hija.</p>
    </div>
    <div class="paso">
      <span class="paso__numero">2</span>
      <h3>Compara</h3>
      <p>Revisa horarios, precios referenciales y distintivos de confianza.</p>
    </div>
    <div class="paso">
      <span class="paso__numero">3</span>
      <h3>Contacta</h3>
      <p>Llama o escribe por WhatsApp directamente desde la ficha.</p>
    </div>
  </div>
</section>

<?php if ($destacadas): ?>
<section class="contenedor seccion-destacadas">
  <h2>Academias en el directorio</h2>
  <div class="tarjetas-negocios">
    <?php foreach ($destacadas as $negocio): ?>
      <a href="/negocio/<?= e($negocio['slug']) ?>" class="tarjeta-negocio">
        <?php if ($negocio['verificado']): ?>
          <span class="insignia insignia--verificada">Verificada por UNE Sports</span>
        <?php else: ?>
          <span class="insignia insignia--no-verificada">Ficha no verificada</span>
        <?php endif; ?>
        <h3><?= e($negocio['nombre_comercial']) ?></h3>
        <p class="tarjeta-negocio__ubicacion"><?= e($negocio['distrito'] ?? '') ?><?= $negocio['distrito'] ? ', ' : '' ?><?= e($negocio['departamento'] ?? '') ?></p>
        <?php if ($negocio['descripcion']): ?>
          <p class="tarjeta-negocio__descripcion"><?= e(mb_substr($negocio['descripcion'], 0, 110)) ?>&hellip;</p>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($regiones): ?>
<section class="contenedor seccion-regiones">
  <h2>Regiones con más registros</h2>
  <div class="grid-regiones">
    <?php foreach ($regiones as $r): ?>
      <a href="/academias/<?= e(generarSlugBase($r['departamento'])) ?>" class="tarjeta-region">
        <strong><?= e($r['departamento']) ?></strong>
        <span><?= (int) $r['total'] ?> academia(s)</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="contenedor seccion-cta-gestor">
  <h2>¿Tienes una academia, escuela o centro deportivo?</h2>
  <p>Regístrala gratis y forma parte del primer directorio nacional de deporte formativo del Perú.</p>
  <a href="/registrar" class="boton boton--primario">Registra tu academia gratis</a>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
