<?php
/**
 * pages/negocio.php — Ficha pública (§8 y §10).
 * $slug llega desde index.php. Nunca usar SELECT * ni exponer los
 * campos de contacto privado (contacto_nombre/cargo/telefono/email,
 * precio_mensual_ref, notas_internas, token_edicion, editando_por).
 */

$stmt = $pdo->prepare(
    "SELECT n.id, n.slug, n.tipo_registro, n.nombre_comercial, n.descripcion,
            n.distrito_id, n.direccion, n.referencia, n.latitud, n.longitud,
            n.telefono_publico, n.telefono_publico_2, n.email_publico, n.web,
            n.facebook, n.instagram, n.tiktok, n.youtube,
            n.modalidad, n.rango_precio, n.tiene_matricula, n.ofrece_beca, n.clase_prueba_gratis,
            n.atiende_genero, n.local_propio, n.seguro_accidentes, n.protocolo_salvaguarda,
            n.personal_certificado, n.requiere_examen_medico, n.afiliacion_federacion,
            n.logo, n.estado, n.verificado, n.vistas,
            dep.nombre AS departamento, prov.nombre AS provincia, dist.nombre AS distrito
     FROM une_negocios n
     LEFT JOIN une_departamentos dep ON dep.id = n.departamento_id
     LEFT JOIN une_provincias prov ON prov.id = n.provincia_id
     LEFT JOIN une_distritos dist ON dist.id = n.distrito_id
     WHERE n.slug = :slug AND n.estado = 'publicado'
     LIMIT 1"
);
$stmt->execute([':slug' => $slug]);
$negocio = $stmt->fetch();

if (!$negocio) {
    http_response_code(404);
    $tituloPagina = 'Ficha no encontrada — ' . SITE_NAME;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="contenedor seccion-error-404"><h1>No encontramos esta ficha</h1><p>Puede que ya no esté publicada.</p><p><a href="/" class="boton boton--primario">Volver al inicio</a></p></section>';
    require __DIR__ . '/../includes/footer.php';
    return;
}

$pdo->prepare('UPDATE une_negocios SET vistas = vistas + 1 WHERE id = :id')->execute([':id' => $negocio['id']]);
registrarEvento($pdo, 'vista_ficha', $negocio['id']);

$stmtCategorias = $pdo->prepare(
    'SELECT c.nombre FROM une_categorias c
     JOIN une_negocio_categorias nc ON nc.categoria_id = c.id
     WHERE nc.negocio_id = :id ORDER BY c.orden'
);
$stmtCategorias->execute([':id' => $negocio['id']]);
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_COLUMN);

$stmtDeportes = $pdo->prepare(
    'SELECT d.nombre, d.icono FROM une_deportes d
     JOIN une_negocio_deportes nd ON nd.deporte_id = d.id
     WHERE nd.negocio_id = :id ORDER BY d.orden'
);
$stmtDeportes->execute([':id' => $negocio['id']]);
$deportes = $stmtDeportes->fetchAll();

$stmtEtapas = $pdo->prepare(
    'SELECT e.nombre, e.rango FROM une_etapas e
     JOIN une_negocio_etapas ne ON ne.etapa_id = e.id
     WHERE ne.negocio_id = :id ORDER BY e.orden'
);
$stmtEtapas->execute([':id' => $negocio['id']]);
$etapas = $stmtEtapas->fetchAll();

$diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$stmtHorarios = $pdo->prepare(
    'SELECT dia_semana, turno, hora_inicio, hora_fin FROM une_horarios WHERE negocio_id = :id ORDER BY dia_semana'
);
$stmtHorarios->execute([':id' => $negocio['id']]);
$horarios = $stmtHorarios->fetchAll();

$stmtImagenes = $pdo->prepare('SELECT archivo, alt FROM une_imagenes WHERE negocio_id = :id ORDER BY orden LIMIT 8');
$stmtImagenes->execute([':id' => $negocio['id']]);
$imagenes = $stmtImagenes->fetchAll();

$stmtRelacionadas = $pdo->prepare(
    "SELECT DISTINCT n2.slug, n2.nombre_comercial
     FROM une_negocios n2
     LEFT JOIN une_negocio_deportes nd2 ON nd2.negocio_id = n2.id
     WHERE n2.estado = 'publicado' AND n2.id != :id
       AND (
            (n2.distrito_id = :distrito_id AND n2.distrito_id IS NOT NULL)
            OR nd2.deporte_id IN (SELECT deporte_id FROM une_negocio_deportes WHERE negocio_id = :id2)
       )
     ORDER BY n2.verificado DESC LIMIT 4"
);
$stmtRelacionadas->execute([
    ':id' => $negocio['id'],
    ':distrito_id' => $negocio['distrito_id'] ?? 0,
    ':id2' => $negocio['id'],
]);
$relacionadas = $stmtRelacionadas->fetchAll();

$telefonoWhatsApp = $negocio['telefono_publico'];
$mensajeWhatsApp = "Hola, vi la ficha de {$negocio['nombre_comercial']} en " . SITE_NAME . " y quisiera más información.";

$tituloPagina = "{$negocio['nombre_comercial']} — Deporte formativo en " . ($negocio['distrito'] ?: $negocio['departamento']) . ' | ' . SITE_NAME;
$metaDescripcion = mb_substr(strip_tags((string) $negocio['descripcion']), 0, 155) ?: "Conoce {$negocio['nombre_comercial']}, en " . ($negocio['distrito'] ?: $negocio['departamento']) . '.';
require __DIR__ . '/../includes/header.php';

$diasSchemaOrg = [
    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
    5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
];
$horariosSchema = array_map(static function (array $h) use ($diasSchemaOrg): array {
    return [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => 'https://schema.org/' . ($diasSchemaOrg[(int) $h['dia_semana']] ?? 'Monday'),
        'opens' => substr($h['hora_inicio'], 0, 5),
        'closes' => substr($h['hora_fin'], 0, 5),
    ];
}, $horarios);

$datosEstructurados = [
    '@context' => 'https://schema.org',
    '@type' => 'SportsActivityLocation',
    'name' => $negocio['nombre_comercial'],
    'description' => $negocio['descripcion'] ?: null,
    'url' => SITE_URL . '/negocio/' . $negocio['slug'],
    'telephone' => $negocio['telefono_publico'] ?: null,
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $negocio['direccion'] ?: null,
        'addressLocality' => $negocio['distrito'] ?: null,
        'addressRegion' => $negocio['departamento'] ?: null,
        'addressCountry' => 'PE',
    ],
];
if ($negocio['latitud'] && $negocio['longitud']) {
    $datosEstructurados['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $negocio['latitud'], 'longitude' => (float) $negocio['longitud']];
}
if ($horariosSchema) {
    $datosEstructurados['openingHoursSpecification'] = $horariosSchema;
}
$sameAs = array_values(array_filter([
    $negocio['web'], $negocio['facebook'], $negocio['instagram'], $negocio['tiktok'], $negocio['youtube'],
]));
if ($sameAs) {
    $datosEstructurados['sameAs'] = $sameAs;
}
$datosEstructurados = array_filter($datosEstructurados, static fn ($v) => $v !== null);
$datosEstructurados['address'] = array_filter($datosEstructurados['address'], static fn ($v) => $v !== null);

$migasPan = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => SITE_URL],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Buscar', 'item' => SITE_URL . '/buscar'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $negocio['nombre_comercial'], 'item' => SITE_URL . '/negocio/' . $negocio['slug']],
    ],
];
?>
<script type="application/ld+json"><?= json_encode($datosEstructurados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/ld+json"><?= json_encode($migasPan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<nav class="migas-pan contenedor" aria-label="Ruta de navegación">
  <a href="/">Inicio</a> › <a href="/buscar">Buscar</a> › <span><?= e($negocio['nombre_comercial']) ?></span>
</nav>

<article class="contenedor ficha-negocio">
  <?php if ($imagenes): ?>
    <div class="ficha-negocio__galeria">
      <?php foreach ($imagenes as $img): ?>
        <img src="/uploads/galeria/<?= e($img['archivo']) ?>" alt="<?= e($img['alt'] ?: $negocio['nombre_comercial']) ?>" loading="lazy" decoding="async" width="400" height="300">
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <header class="ficha-negocio__cabecera">
    <?php if ($negocio['logo']): ?>
      <img src="/uploads/logos/<?= e($negocio['logo']) ?>" alt="Logo de <?= e($negocio['nombre_comercial']) ?>" class="ficha-negocio__logo" width="88" height="88">
    <?php endif; ?>
    <?php if ($negocio['verificado']): ?>
      <span class="insignia insignia--verificada">Verificada por UNE Sports</span>
    <?php else: ?>
      <span class="insignia insignia--no-verificada">Ficha no verificada</span>
    <?php endif; ?>
    <h1><?= e($negocio['nombre_comercial']) ?></h1>
    <p class="ficha-negocio__ubicacion">
      <?= e($negocio['direccion'] ?: '') ?><?= $negocio['direccion'] ? ' — ' : '' ?>
      <?= e($negocio['distrito'] ?: '') ?><?= $negocio['distrito'] ? ', ' : '' ?><?= e($negocio['departamento'] ?: '') ?>
    </p>

    <?php if (!$negocio['verificado']): ?>
      <div class="alerta alerta--info">
        Datos recopilados por el equipo de <?= e(SITE_NAME) ?>. El establecimiento aún no ha confirmado esta información.
        <a href="/reclamar/<?= e($negocio['slug']) ?>">¿Eres el responsable? Confirma y completa tu ficha gratis</a>.
      </div>
    <?php endif; ?>

    <?php if ($categorias): ?>
      <div class="etiquetas">
        <?php foreach ($categorias as $cat): ?><span class="etiqueta"><?= e($cat) ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($deportes): ?>
      <div class="etiquetas">
        <?php foreach ($deportes as $dep): ?><span class="etiqueta"><?= e($dep['icono']) ?> <?= e($dep['nombre']) ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($etapas): ?>
      <div class="etiquetas etiquetas--etapas">
        <?php foreach ($etapas as $et): ?><span class="etiqueta etiqueta--etapa"><?= e($et['nombre']) ?> (<?= e($et['rango']) ?>)</span><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php
      $modalidadTexto = ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'mixta' => 'Presencial y virtual'][$negocio['modalidad']] ?? null;
      $generoTexto = ['mixto' => 'Mixto (niños y niñas)', 'femenino' => 'Solo niñas', 'masculino' => 'Solo niños'][$negocio['atiende_genero']] ?? null;
    ?>
    <?php if ($modalidadTexto || $generoTexto): ?>
      <div class="etiquetas">
        <?php if ($modalidadTexto): ?><span class="etiqueta"><?= e($modalidadTexto) ?></span><?php endif; ?>
        <?php if ($generoTexto): ?><span class="etiqueta"><?= e($generoTexto) ?></span><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($negocio['rango_precio']): ?>
      <p class="rango-precio" aria-label="Rango de precio">
        <?= str_repeat('S/ ', (int) $negocio['rango_precio']) ?><span class="rango-precio__resto"><?= str_repeat('· ', 4 - (int) $negocio['rango_precio']) ?></span>
      </p>
    <?php endif; ?>

    <div class="acciones-contacto">
      <?php if ($negocio['telefono_publico']): ?>
        <a href="tel:+51<?= e($negocio['telefono_publico']) ?>" class="boton boton--primario" data-evento="clic_telefono" data-negocio="<?= (int) $negocio['id'] ?>">Llamar</a>
        <a href="<?= e(enlaceWhatsApp($telefonoWhatsApp, $mensajeWhatsApp)) ?>" target="_blank" rel="noopener" class="boton boton--secundario" data-evento="clic_whatsapp" data-negocio="<?= (int) $negocio['id'] ?>">WhatsApp</a>
      <?php endif; ?>
      <?php if ($negocio['email_publico']): ?>
        <a href="mailto:<?= e($negocio['email_publico']) ?>" class="boton boton--texto">Escribir por correo</a>
      <?php endif; ?>
      <button type="button" class="boton boton--texto" id="boton-compartir-ficha" data-negocio="<?= (int) $negocio['id'] ?>" data-url="<?= e(SITE_URL . '/negocio/' . $negocio['slug']) ?>">Compartir</button>
    </div>

    <?php if ($negocio['telefono_publico_2']): ?>
      <p class="texto-ayuda">También puedes llamar al <a href="tel:+51<?= e($negocio['telefono_publico_2']) ?>"><?= e($negocio['telefono_publico_2']) ?></a>.</p>
    <?php endif; ?>

    <?php
      $redesNegocio = array_filter([
          'facebook' => $negocio['facebook'],
          'instagram' => $negocio['instagram'],
          'tiktok' => $negocio['tiktok'],
          'youtube' => $negocio['youtube'],
      ]);
    ?>
    <?php if ($negocio['web'] || $redesNegocio): ?>
      <div class="ficha-negocio__redes">
        <?php if ($negocio['web']): ?><a href="<?= e($negocio['web']) ?>" target="_blank" rel="noopener noreferrer">Sitio web</a><?php endif; ?>
        <?php foreach ($redesNegocio as $red => $url): ?>
          <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($red)) ?>"><?= iconoRedSocial($red) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </header>

  <?php if (!empty($negocio['descripcion'])): ?>
    <section class="ficha-negocio__seccion">
      <h2>Sobre nosotros</h2>
      <p><?= nl2br(e($negocio['descripcion'])) ?></p>
    </section>
  <?php endif; ?>

  <?php if ($horarios): ?>
    <section class="ficha-negocio__seccion">
      <h2>Horarios</h2>
      <table class="tabla-horarios">
        <thead><tr><th>Día</th><th>Turno</th><th>Horario</th></tr></thead>
        <tbody>
          <?php foreach ($horarios as $h): ?>
            <tr>
              <td><?= e($diasSemana[(int) $h['dia_semana']] ?? '') ?></td>
              <td><?= e(ucfirst($h['turno'])) ?></td>
              <td><?= e(substr($h['hora_inicio'], 0, 5)) ?> – <?= e(substr($h['hora_fin'], 0, 5)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <?php
    $distintivos = array_filter([
        $negocio['local_propio'] ? 'Local propio' : null,
        $negocio['seguro_accidentes'] ? 'Seguro contra accidentes' : null,
        $negocio['protocolo_salvaguarda'] ? 'Protocolo de salvaguarda infantil' : null,
        $negocio['personal_certificado'] ? 'Personal certificado' : null,
        $negocio['clase_prueba_gratis'] ? 'Clase de prueba gratis' : null,
        $negocio['ofrece_beca'] ? 'Ofrece becas o descuentos' : null,
        $negocio['requiere_examen_medico'] ? 'Requiere examen médico' : null,
        $negocio['tiene_matricula'] ? 'Requiere pago de matrícula' : null,
        $negocio['afiliacion_federacion'] ? 'Afiliado a: ' . $negocio['afiliacion_federacion'] : null,
    ]);
  ?>
  <?php if ($distintivos): ?>
    <section class="ficha-negocio__seccion">
      <h2>Confianza</h2>
      <ul class="lista-distintivos">
        <?php foreach ($distintivos as $d): ?><li><?= e($d) ?></li><?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if ($negocio['latitud'] && $negocio['longitud']): ?>
    <section class="ficha-negocio__seccion">
      <h2>Ubicación</h2>
      <div id="mapa-ficha" class="mapa-ficha" data-lat="<?= e((string) $negocio['latitud']) ?>" data-lng="<?= e((string) $negocio['longitud']) ?>" data-nombre="<?= e($negocio['nombre_comercial']) ?>"></div>
      <?php if ($negocio['referencia']): ?><p class="texto-ayuda"><?= e($negocio['referencia']) ?></p><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($relacionadas): ?>
    <section class="ficha-negocio__seccion">
      <h2>Academias relacionadas</h2>
      <ul class="lista-relacionadas">
        <?php foreach ($relacionadas as $r): ?>
          <li><a href="/negocio/<?= e($r['slug']) ?>"><?= e($r['nombre_comercial']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="ficha-negocio__seccion ficha-negocio__legal">
    <a href="/solicitar-retiro/<?= e($negocio['slug']) ?>">Solicitar corrección o retiro de esta ficha</a>
  </section>
</article>

<?php if ($negocio['latitud'] && $negocio['longitud']): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="/assets/js/mapa.js" defer></script>
<?php endif; ?>
<script>
window.UNE_NEGOCIO_ID = <?= (int) $negocio['id'] ?>;
</script>
<?php
require __DIR__ . '/../includes/footer.php';
