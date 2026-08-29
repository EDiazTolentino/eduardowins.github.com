<?php
/**
 * pages/nosotros.php — Misión, visión, historia y equipo.
 * El grid de equipo se alimenta de une_equipo; si está vacía (todavía no
 * hay CRUD para esa tabla en el panel — se administra por phpMyAdmin),
 * la sección se omite en vez de mostrar datos inventados.
 */

$equipo = $pdo->query('SELECT nombre, cargo, bio, foto, linkedin FROM une_equipo ORDER BY orden')->fetchAll();

$tituloPagina = 'Nosotros — ' . SITE_NAME;
$metaDescripcion = 'Conoce la misión, visión e historia de UNE Sports Perú, el directorio nacional de deporte formativo.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta">
  <h1>Sobre UNE Sports Perú</h1>

  <h2>El problema que resolvemos</h2>
  <p>El deporte formativo en el Perú está fragmentado y es invisible. Hoy en día, nos enfrentamos a un doble desafío:</p>
  <ul class="lista-vinetas">
    <li><strong>Para los profesionales y academias:</strong> Miles de técnicos, entusiastas y centros de servicios deportivos realizan un trabajo silencioso y vital impactando la vida de los niños, pero carecen de una plataforma que les brinde visibilidad.</li>
    <li><strong>Para las familias:</strong> Los padres no tienen un lugar centralizado y confiable donde buscar opciones deportivas para sus hijos. Como resultado, la exposición deportiva de los jóvenes suele limitarse al fútbol o al vóley, dejando en la sombra decenas de otras disciplinas fundamentales para el desarrollo integral.</li>
  </ul>

  <h2>Misión</h2>
  <p>Conectar a las familias peruanas con la oferta de deporte formativo de su zona, y darle a cada academia, escuela y centro deportivo un lugar gratuito donde ser encontrado.</p>

  <h2>Visión</h2>
  <p>Ser el directorio de referencia del deporte formativo en el Perú, cubriendo los 25 departamentos con información completa y confiable sobre cada academia registrada.</p>

  <h2>Cómo trabajamos</h2>
  <p>Operamos bajo un modelo transparente y colaborativo diseñado para construir comunidad:</p>
  <ol class="lista-numerada">
    <li><strong>Registro 100% gratuito (y para siempre):</strong> Eliminamos las barreras de entrada. Cualquier academia, escuela o centro de servicio deportivo puede crear su perfil sin costo, garantizando igualdad de oportunidades en su visibilidad.</li>
    <li><strong>Mapeo del ecosistema:</strong> Nuestro primer gran hito es identificar y cuantificar la realidad del deporte formativo peruano. Queremos saber exactamente cuántos somos, dónde estamos y qué enseñamos.</li>
    <li><strong>Diversificación deportiva:</strong> Filtramos y categorizamos la información para que los padres puedan explorar más allá de los deportes tradicionales, descubriendo nuevas disciplinas adaptadas al talento y gusto de sus hijos.</li>
    <li><strong>Conexión directa:</strong> Diseñamos una plataforma intuitiva donde, con un par de clics, las familias pueden ubicar por distrito y categoría el centro deportivo o de servicio que mejor se adapte a sus necesidades.</li>
  </ol>
</section>

<?php if ($equipo): ?>
<section class="contenedor seccion-equipo">
  <h2>Nuestro equipo</h2>
  <div class="grid-equipo">
    <?php foreach ($equipo as $persona): ?>
      <div class="tarjeta-persona">
        <?php if ($persona['foto']): ?>
          <img src="/uploads/logos/<?= e($persona['foto']) ?>" alt="<?= e($persona['nombre']) ?>" width="120" height="120">
        <?php endif; ?>
        <h3><?= e($persona['nombre']) ?></h3>
        <?php if ($persona['cargo']): ?><p class="tarjeta-persona__cargo"><?= e($persona['cargo']) ?></p><?php endif; ?>
        <?php if ($persona['bio']): ?><p><?= e($persona['bio']) ?></p><?php endif; ?>
        <?php if ($persona['linkedin']): ?><a href="<?= e($persona['linkedin']) ?>" target="_blank" rel="noopener">LinkedIn</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="contenedor seccion-cta-gestor">
  <h2>¿Quieres ser parte del directorio?</h2>
  <a href="/registrar" class="boton boton--primario">Registra tu academia gratis</a>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
