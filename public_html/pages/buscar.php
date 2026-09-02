<?php
/**
 * pages/buscar.php — Buscador con filtros combinados y vista lista/mapa
 * (Fase 2, §8). Los filtros se reflejan en la URL para que sean
 * compartibles e indexables. Nunca se usa SELECT * (ficha pública).
 */

$dep = (int) ($_GET['dep'] ?? 0) ?: null;
$prov = (int) ($_GET['prov'] ?? 0) ?: null;
$dist = (int) ($_GET['dist'] ?? 0) ?: null;
$tipo = in_array($_GET['tipo'] ?? '', ['formativo', 'servicio'], true) ? $_GET['tipo'] : null;
$categoria = (int) ($_GET['categoria'] ?? 0) ?: null;
$deporte = (int) ($_GET['deporte'] ?? 0) ?: null;
$etapa = (int) ($_GET['etapa'] ?? 0) ?: null;
$turno = in_array($_GET['turno'] ?? '', ['mañana', 'tarde', 'noche'], true) ? $_GET['turno'] : null;
$precio = (int) ($_GET['precio'] ?? 0) ?: null;
$verificado = isset($_GET['verificado']);
$localPropio = isset($_GET['local_propio']);
$pruebaGratis = isset($_GET['prueba_gratis']);
$q = trim((string) ($_GET['q'] ?? ''));
$vista = ($_GET['vista'] ?? 'lista') === 'mapa' ? 'mapa' : 'lista';
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 12;

$condiciones = ["n.estado = 'publicado'"];
$params = [];

if ($dep) { $condiciones[] = 'n.departamento_id = :dep'; $params[':dep'] = $dep; }
if ($prov) { $condiciones[] = 'n.provincia_id = :prov'; $params[':prov'] = $prov; }
if ($dist) { $condiciones[] = 'n.distrito_id = :dist'; $params[':dist'] = $dist; }
if ($tipo) { $condiciones[] = 'n.tipo_registro = :tipo'; $params[':tipo'] = $tipo; }
if ($categoria) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_negocio_categorias nc WHERE nc.negocio_id = n.id AND nc.categoria_id = :categoria)';
    $params[':categoria'] = $categoria;
}
if ($deporte) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_negocio_deportes nd WHERE nd.negocio_id = n.id AND nd.deporte_id = :deporte)';
    $params[':deporte'] = $deporte;
}
if ($etapa) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_negocio_etapas ne WHERE ne.negocio_id = n.id AND ne.etapa_id = :etapa)';
    $params[':etapa'] = $etapa;
}
if ($turno) {
    $condiciones[] = 'EXISTS (SELECT 1 FROM une_horarios h WHERE h.negocio_id = n.id AND h.turno = :turno)';
    $params[':turno'] = $turno;
}
if ($precio) { $condiciones[] = 'n.rango_precio = :precio'; $params[':precio'] = $precio; }
if ($verificado) { $condiciones[] = 'n.verificado = 1'; }
if ($localPropio) { $condiciones[] = 'n.local_propio = 1'; }
if ($pruebaGratis) { $condiciones[] = 'n.clase_prueba_gratis = 1'; }
if ($q !== '') {
    $condiciones[] = '(n.nombre_comercial LIKE :q1 OR dist.nombre LIKE :q2 OR dep.nombre LIKE :q3
        OR EXISTS (
            SELECT 1 FROM une_negocio_deportes nd2
            JOIN une_deportes d2 ON d2.id = nd2.deporte_id
            WHERE nd2.negocio_id = n.id AND d2.nombre LIKE :q4
        ))';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
    $params[':q4'] = '%' . $q . '%';
}

$sqlDesde = 'FROM une_negocios n
             LEFT JOIN une_departamentos dep ON dep.id = n.departamento_id
             LEFT JOIN une_distritos dist ON dist.id = n.distrito_id
             WHERE ' . implode(' AND ', $condiciones);

$stmtTotal = $pdo->prepare('SELECT COUNT(*) ' . $sqlDesde);
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare(
    'SELECT n.slug, n.nombre_comercial, n.descripcion, n.verificado, n.rango_precio, n.clase_prueba_gratis,
            n.latitud, n.longitud, n.logo,
            dep.nombre AS departamento, dist.nombre AS distrito
     FROM une_negocios n
     LEFT JOIN une_departamentos dep ON dep.id = n.departamento_id
     LEFT JOIN une_distritos dist ON dist.id = n.distrito_id
     WHERE ' . implode(' AND ', $condiciones) . "
     ORDER BY n.verificado DESC, n.completitud DESC, n.nombre_comercial ASC
     LIMIT {$porPagina} OFFSET {$offset}"
);
$stmt->execute($params);
$resultados = $stmt->fetchAll();

$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo')->fetchAll();
$provincias = [];
if ($dep) {
    $stmtProv = $pdo->prepare('SELECT id, nombre FROM une_provincias WHERE departamento_id = :dep AND activo = 1 ORDER BY nombre');
    $stmtProv->execute([':dep' => $dep]);
    $provincias = $stmtProv->fetchAll();
}
$distritos = [];
if ($prov) {
    $stmtDist = $pdo->prepare('SELECT id, nombre FROM une_distritos WHERE provincia_id = :prov AND activo = 1 ORDER BY nombre');
    $stmtDist->execute([':prov' => $prov]);
    $distritos = $stmtDist->fetchAll();
}
$categorias = $pdo->query('SELECT id, nombre, tipo_registro FROM une_categorias ORDER BY tipo_registro, orden')->fetchAll();
$deportesCat = $pdo->query('SELECT id, nombre, icono FROM une_deportes ORDER BY orden')->fetchAll();
$etapasCat = $pdo->query('SELECT id, nombre, rango FROM une_etapas ORDER BY orden')->fetchAll();

function conservarFiltros(array $extra = []): string
{
    $actuales = $_GET;
    unset($actuales['pagina']);
    return http_build_query(array_merge($actuales, $extra));
}

$tituloPagina = 'Buscar academias y centros deportivos — ' . SITE_NAME;
$metaDescripcion = 'Filtra academias, escuelas y centros de deporte formativo por ubicación, disciplina, etapa y más en todo el Perú.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-buscar">
  <h1>Buscar academias y centros</h1>

  <form method="get" class="formulario-filtros formulario-filtros--buscar" id="formulario-buscar">
    <input type="hidden" name="vista" value="<?= e($vista) ?>">
    <input type="search" name="q" placeholder="Nombre de la academia" value="<?= e($q) ?>">

    <select name="dep" onchange="this.form.submit()">
      <option value="">Todo el Perú</option>
      <?php foreach ($departamentos as $d): ?>
        <option value="<?= (int) $d['id'] ?>" <?= $dep === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="prov" onchange="this.form.submit()" <?= $provincias ? '' : 'disabled' ?>>
      <option value="">Toda la provincia</option>
      <?php foreach ($provincias as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= $prov === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="dist" <?= $distritos ? '' : 'disabled' ?>>
      <option value="">Todo el distrito</option>
      <?php foreach ($distritos as $d): ?>
        <option value="<?= (int) $d['id'] ?>" <?= $dist === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="tipo">
      <option value="">Formativo y servicio</option>
      <option value="formativo" <?= $tipo === 'formativo' ? 'selected' : '' ?>>Formativo</option>
      <option value="servicio" <?= $tipo === 'servicio' ? 'selected' : '' ?>>Servicio</option>
    </select>
    <select name="deporte">
      <option value="">Cualquier disciplina</option>
      <?php foreach ($deportesCat as $dp): ?>
        <option value="<?= (int) $dp['id'] ?>" <?= $deporte === (int) $dp['id'] ? 'selected' : '' ?>><?= e($dp['icono']) ?> <?= e($dp['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="etapa">
      <option value="">Cualquier etapa</option>
      <?php foreach ($etapasCat as $et): ?>
        <option value="<?= (int) $et['id'] ?>" <?= $etapa === (int) $et['id'] ? 'selected' : '' ?>><?= e($et['nombre']) ?> (<?= e($et['rango']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="turno">
      <option value="">Cualquier turno</option>
      <option value="mañana" <?= $turno === 'mañana' ? 'selected' : '' ?>>Mañana</option>
      <option value="tarde" <?= $turno === 'tarde' ? 'selected' : '' ?>>Tarde</option>
      <option value="noche" <?= $turno === 'noche' ? 'selected' : '' ?>>Noche</option>
    </select>
    <select name="precio">
      <option value="">Cualquier precio</option>
      <?php for ($n = 1; $n <= 4; $n++): ?>
        <option value="<?= $n ?>" <?= $precio === $n ? 'selected' : '' ?>><?= str_repeat('S/ ', $n) ?></option>
      <?php endfor; ?>
    </select>

    <label class="opcion-casilla"><input type="checkbox" name="verificado" value="1" <?= $verificado ? 'checked' : '' ?>> Solo verificadas</label>
    <label class="opcion-casilla"><input type="checkbox" name="local_propio" value="1" <?= $localPropio ? 'checked' : '' ?>> Local propio</label>
    <label class="opcion-casilla"><input type="checkbox" name="prueba_gratis" value="1" <?= $pruebaGratis ? 'checked' : '' ?>> Clase de prueba gratis</label>

    <button type="submit" class="boton boton--primario">Filtrar</button>
  </form>

  <div class="alternador-vista">
    <a href="?<?= conservarFiltros(['vista' => 'lista']) ?>" class="<?= $vista === 'lista' ? 'activo' : '' ?>">Lista</a>
    <a href="?<?= conservarFiltros(['vista' => 'mapa']) ?>" class="<?= $vista === 'mapa' ? 'activo' : '' ?>">Mapa</a>
  </div>

  <p class="texto-ayuda"><?= $total ?> resultado(s)</p>

  <?php
    $nombreDistritoFiltro = null;
    if ($dist) {
        foreach ($distritos as $d) {
            if ((int) $d['id'] === $dist) { $nombreDistritoFiltro = $d['nombre']; break; }
        }
    }
  ?>
  <?php if (!$resultados): ?>
    <div class="alerta alerta--info">
      No encontramos academias con esos filtros<?= $nombreDistritoFiltro ? ' en ' . e($nombreDistritoFiltro) : '' ?>.
      ¿Conoces alguna? <a href="/sugerir">Sugiérela aquí</a>.
    </div>
  <?php elseif ($vista === 'lista'): ?>
    <div class="tarjetas-negocios">
      <?php foreach ($resultados as $n): ?>
        <a href="/negocio/<?= e($n['slug']) ?>" class="tarjeta-negocio">
          <?php if ($n['logo']): ?>
            <img src="/uploads/logos/<?= e($n['logo']) ?>" alt="" class="tarjeta-negocio__logo" width="56" height="56" loading="lazy">
          <?php endif; ?>
          <?php if ($n['verificado']): ?>
            <span class="insignia insignia--verificada">Verificada</span>
          <?php else: ?>
            <span class="insignia insignia--no-verificada">No verificada</span>
          <?php endif; ?>
          <h3><?= e($n['nombre_comercial']) ?></h3>
          <p class="tarjeta-negocio__ubicacion"><?= e($n['distrito'] ?? '') ?><?= $n['distrito'] ? ', ' : '' ?><?= e($n['departamento'] ?? '') ?></p>
          <?php if ($n['rango_precio']): ?><p class="rango-precio"><?= str_repeat('S/ ', (int) $n['rango_precio']) ?></p><?php endif; ?>
          <?php if ($n['clase_prueba_gratis']): ?><span class="etiqueta">Clase de prueba gratis</span><?php endif; ?>
          <?php if ($n['descripcion']): ?><p class="tarjeta-negocio__descripcion"><?= e(mb_substr($n['descripcion'], 0, 100)) ?>&hellip;</p><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
      <nav class="paginacion">
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
          <a href="?<?= conservarFiltros(['pagina' => $p]) ?>" class="<?= $p === $pagina ? 'activo' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div id="mapa-buscar" class="mapa-buscar" data-fuente="/api/buscar.php?<?= http_build_query(array_diff_key($_GET, ['vista' => '', 'pagina' => ''])) ?>"></div>
  <?php endif; ?>

  <p class="texto-ayuda seccion-sugerir-footer">
    ¿Conoces una academia que no aparece aquí? <a href="/sugerir">Sugiérela aquí</a>.
  </p>
</section>
<?php if ($vista === 'mapa'): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script src="/assets/js/mapa-buscar.js" defer></script>
<?php endif; ?>
<?php
require __DIR__ . '/../includes/footer.php';
