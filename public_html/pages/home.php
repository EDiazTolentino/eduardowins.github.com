<?php
/**
 * pages/home.php — Inicio (versión Fase 1: enfocada en captación).
 * El buscador completo, el mapa y las páginas geográficas llegan en la
 * Fase 2 (§13); esta versión ya conecta con fichas reales vía /negocio/{slug}.
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

$tituloPagina = SITE_NAME . ' — Directorio Nacional de Deporte Formativo';
require __DIR__ . '/../includes/header.php';
?>
<section class="hero">
  <div class="contenedor hero__interior">
    <h1>Encuentra la academia deportiva ideal para tu hijo o hija</h1>
    <p class="hero__subtitulo">El directorio nacional de academias, escuelas y centros de deporte formativo del Perú, para niños y adolescentes de 4 a 18 años.</p>
    <p class="hero__contador">
      <strong><?= (int) $contador['total'] ?></strong> academias registradas en
      <strong><?= (int) $contador['distritos'] ?></strong> distritos del Perú
    </p>
    <a href="/registrar" class="boton boton--primario boton--grande">Registra tu academia gratis</a>
  </div>
</section>

<section class="contenedor seccion-como-funciona">
  <h2>¿Cómo funciona?</h2>
  <div class="pasos">
    <div class="paso">
      <span class="paso__numero">1</span>
      <h3>Regístrate</h3>
      <p>Deja el nombre y teléfono de tu academia en menos de 30 segundos.</p>
    </div>
    <div class="paso">
      <span class="paso__numero">2</span>
      <h3>Te llamamos</h3>
      <p>Nuestro equipo te contacta para completar tu ficha con toda la información.</p>
    </div>
    <div class="paso">
      <span class="paso__numero">3</span>
      <h3>Te encuentran</h3>
      <p>Tu ficha queda publicada gratis para que las familias de tu zona te encuentren.</p>
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

<section class="contenedor seccion-cta-gestor">
  <h2>¿Tienes una academia, escuela o centro deportivo?</h2>
  <p>Regístrala gratis y forma parte del primer directorio nacional de deporte formativo del Perú.</p>
  <a href="/registrar" class="boton boton--primario">Registra tu academia gratis</a>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
