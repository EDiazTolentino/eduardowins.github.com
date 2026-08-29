<?php
/**
 * admin/negocios.php — Listado general de negocios (leads y fichas),
 * con filtros y paginación. Complementa a leads.php (que se enfoca en
 * la cola de trabajo comercial) con una vista completa de la base.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

exigirLoginAdmin();
$pdo = BaseDatos::obtener();

$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    if (($_POST['accion'] ?? '') === 'eliminar') {
        exigirRolAdministrador();
        $eliminarId = (int) $_POST['id'];

        $stmtLogo = $pdo->prepare('SELECT logo FROM une_negocios WHERE id = :id');
        $stmtLogo->execute([':id' => $eliminarId]);
        $logo = $stmtLogo->fetchColumn();
        if ($logo) {
            @unlink(RUTA_UPLOADS . '/logos/' . $logo);
        }
        $stmtImgs = $pdo->prepare('SELECT archivo FROM une_imagenes WHERE negocio_id = :id');
        $stmtImgs->execute([':id' => $eliminarId]);
        foreach ($stmtImgs->fetchAll(PDO::FETCH_COLUMN) as $archivo) {
            @unlink(RUTA_UPLOADS . '/galeria/' . $archivo);
        }

        foreach (['une_negocio_categorias', 'une_negocio_deportes', 'une_negocio_etapas', 'une_horarios', 'une_imagenes', 'une_lead_historial'] as $tabla) {
            $pdo->prepare("DELETE FROM {$tabla} WHERE negocio_id = :id")->execute([':id' => $eliminarId]);
        }
        $pdo->prepare('DELETE FROM une_negocios WHERE id = :id')->execute([':id' => $eliminarId]);
        $mensaje = 'Negocio eliminado permanentemente.';
    }
}

$filtroEstado = $_GET['estado'] ?? '';
$filtroTipo = $_GET['tipo_registro'] ?? '';
$filtroDepartamento = $_GET['departamento_id'] ?? '';
$filtroVerificado = $_GET['verificado'] ?? '';
$busqueda = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 20;

$condiciones = ['1=1'];
$params = [];
if ($filtroEstado) { $condiciones[] = 'n.estado = :estado'; $params[':estado'] = $filtroEstado; }
if ($filtroTipo) { $condiciones[] = 'n.tipo_registro = :tipo'; $params[':tipo'] = $filtroTipo; }
if ($filtroDepartamento) { $condiciones[] = 'n.departamento_id = :dep'; $params[':dep'] = $filtroDepartamento; }
if ($filtroVerificado !== '') { $condiciones[] = 'n.verificado = :verificado'; $params[':verificado'] = (int) $filtroVerificado; }
if ($busqueda !== '') {
    $condiciones[] = '(n.nombre_comercial LIKE :busqueda OR n.telefono_publico LIKE :busqueda2)';
    $params[':busqueda'] = '%' . $busqueda . '%';
    $params[':busqueda2'] = '%' . $busqueda . '%';
}

$sqlBase = 'FROM une_negocios n LEFT JOIN une_departamentos dep ON dep.id = n.departamento_id
            LEFT JOIN une_distritos dist ON dist.id = n.distrito_id
            WHERE ' . implode(' AND ', $condiciones);

$stmtTotal = $pdo->prepare('SELECT COUNT(*) ' . $sqlBase);
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare(
    'SELECT n.id, n.slug, n.nombre_comercial, n.tipo_registro, n.estado, n.verificado, n.completitud,
            n.origen, n.creado_en, dep.nombre AS departamento, dist.nombre AS distrito
     ' . $sqlBase . " ORDER BY n.creado_en DESC LIMIT {$porPagina} OFFSET {$offset}"
);
$stmt->execute($params);
$negocios = $stmt->fetchAll();

$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo')->fetchAll();

$tituloPagina = 'Negocios — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <div class="admin-encabezado">
    <h1>Todos los negocios <small>(<?= $total ?>)</small></h1>
    <a href="/admin/negocio-editar.php?nuevo=1" class="boton boton--primario">+ Nuevo negocio</a>
  </div>
  <?php if ($mensaje): ?><p class="alerta alerta--exito"><?= e($mensaje) ?></p><?php endif; ?>

  <form method="get" class="formulario-filtros">
    <input type="search" name="q" placeholder="Buscar por nombre o teléfono" value="<?= e($busqueda) ?>">
    <select name="estado">
      <option value="">Todos los estados</option>
      <?php foreach (['lead', 'en_gestion', 'en_revision', 'publicado', 'rechazado', 'duplicado', 'no_contactable'] as $est): ?>
        <option value="<?= e($est) ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $est)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="tipo_registro">
      <option value="">Formativo y servicio</option>
      <option value="formativo" <?= $filtroTipo === 'formativo' ? 'selected' : '' ?>>Formativo</option>
      <option value="servicio" <?= $filtroTipo === 'servicio' ? 'selected' : '' ?>>Servicio</option>
    </select>
    <select name="departamento_id">
      <option value="">Todos los departamentos</option>
      <?php foreach ($departamentos as $d): ?>
        <option value="<?= (int) $d['id'] ?>" <?= (string) $filtroDepartamento === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="verificado">
      <option value="">Verificadas y no verificadas</option>
      <option value="1" <?= $filtroVerificado === '1' ? 'selected' : '' ?>>Solo verificadas</option>
      <option value="0" <?= $filtroVerificado === '0' ? 'selected' : '' ?>>Solo no verificadas</option>
    </select>
    <button type="submit" class="boton boton--secundario">Filtrar</button>
  </form>

  <div class="tabla-responsiva">
  <table class="tabla-admin">
    <thead>
      <tr><th>Negocio</th><th>Tipo</th><th>Ubicación</th><th>Estado</th><th>Verificada</th><th>Completitud</th><th>Origen</th><th>Registrado</th><th>Acciones</th></tr>
    </thead>
    <tbody>
      <?php foreach ($negocios as $n): ?>
        <tr>
          <td><a href="/admin/negocio-editar.php?id=<?= (int) $n['id'] ?>"><?= e($n['nombre_comercial']) ?></a></td>
          <td><?= e($n['tipo_registro']) ?></td>
          <td><?= e($n['distrito'] ?? '') ?><?= $n['distrito'] ? ', ' : '' ?><?= e($n['departamento'] ?? '') ?></td>
          <td><?= e(str_replace('_', ' ', $n['estado'])) ?></td>
          <td><?= $n['verificado'] ? 'Sí' : 'No' ?></td>
          <td><?= (int) $n['completitud'] ?>%</td>
          <td><?= e(str_replace('_', ' ', $n['origen'])) ?></td>
          <td><?= e(tiempoRelativo($n['creado_en'])) ?></td>
          <td>
            <a href="/admin/negocio-editar.php?id=<?= (int) $n['id'] ?>">Editar</a>
            <?php if ($n['estado'] === 'publicado'): ?>
              · <a href="/negocio/<?= e($n['slug']) ?>" target="_blank" rel="noopener">Ver ficha</a>
            <?php endif; ?>
            <?php if (adminEsAdministrador()): ?>
              · <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este negocio permanentemente? Esta acción no se puede deshacer.');">
                  <?= csrfCampo() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                  <button type="submit" class="boton-enlace boton-enlace--peligro">Eliminar</button>
                </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$negocios): ?><tr><td colspan="9">No hay negocios que coincidan con este filtro.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($totalPaginas > 1): ?>
    <nav class="paginacion">
      <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>" class="<?= $p === $pagina ? 'activo' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
<?php
require __DIR__ . '/../includes/admin-footer.php';
