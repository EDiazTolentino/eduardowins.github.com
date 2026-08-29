<?php
/**
 * admin/leads.php — Bandeja de leads (§7D): cola de trabajo diaria.
 * Vista por defecto: leads sin contactar + leads con seguimiento vencido.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

exigirLoginAdmin();
$pdo = BaseDatos::obtener();

$mensaje = null;

// ---------------------------------------------------------------------
// Acciones (registrar contacto individual, acciones en lote)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar_contacto') {
        $negocioId = (int) $_POST['negocio_id'];
        $resultado = $_POST['resultado_contacto'] ?? 'sin_contactar';
        $proximoSeguimiento = ($_POST['proximo_seguimiento'] ?? '') ?: null;
        $nota = trim((string) ($_POST['nota'] ?? ''));
        $tipoContacto = $_POST['tipo_contacto'] ?? 'llamada';

        $resultadosValidos = ['sin_contactar', 'no_contesta', 'numero_errado', 'interesado', 'en_espera', 'rechazo'];
        if (in_array($resultado, $resultadosValidos, true)) {
            $pdo->prepare(
                'UPDATE une_negocios SET
                    resultado_contacto = :resultado,
                    intentos_contacto = intentos_contacto + 1,
                    ultimo_contacto = NOW(),
                    proximo_seguimiento = :proximo,
                    estado = IF(estado = \'lead\', \'en_gestion\', estado)
                 WHERE id = :id'
            )->execute([':resultado' => $resultado, ':proximo' => $proximoSeguimiento, ':id' => $negocioId]);

            $pdo->prepare(
                'INSERT INTO une_lead_historial (negocio_id, admin_id, accion, resultado, nota) VALUES (:id, :admin, :accion, :resultado, :nota)'
            )->execute([
                ':id' => $negocioId, ':admin' => adminId(), ':accion' => $tipoContacto,
                ':resultado' => $resultado, ':nota' => $nota ?: null,
            ]);
            $mensaje = 'Contacto registrado.';
        }
    }

    if ($accion === 'lote_asignar' && adminEsAdministrador()) {
        $ids = array_map('intval', $_POST['negocio_ids'] ?? []);
        $asignadoId = (int) ($_POST['admin_asignado_id'] ?? 0) ?: null;
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE une_negocios SET admin_asignado_id = ? WHERE id IN ($in)");
            $stmt->execute(array_merge([$asignadoId], $ids));
            $mensaje = count($ids) . ' lead(s) asignado(s).';
        }
    }

    if ($accion === 'lote_estado') {
        $ids = array_map('intval', $_POST['negocio_ids'] ?? []);
        $nuevoEstado = $_POST['nuevo_estado'] ?? '';
        $estadosPermitidos = ['en_gestion', 'no_contactable', 'duplicado', 'rechazado'];
        if ($ids && in_array($nuevoEstado, $estadosPermitidos, true)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE une_negocios SET estado = ? WHERE id IN ($in)");
            $stmt->execute(array_merge([$nuevoEstado], $ids));
            foreach ($ids as $id) {
                $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion, resultado, nota) VALUES (:id, :admin, \'cambio_estado\', :estado, \'Cambio en lote desde la bandeja de leads.\')')
                    ->execute([':id' => $id, ':admin' => adminId(), ':estado' => $nuevoEstado]);
            }
            $mensaje = count($ids) . ' lead(s) actualizado(s).';
        }
    }
}

// ---------------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------------
$vista = $_GET['vista'] ?? 'pendientes';
$filtroEstado = $_GET['estado'] ?? '';
$filtroOrigen = $_GET['origen'] ?? '';
$filtroAsignado = $_GET['admin_asignado_id'] ?? '';
$filtroDepartamento = $_GET['departamento_id'] ?? '';
$filtroResultado = $_GET['resultado_contacto'] ?? '';
$busqueda = trim((string) ($_GET['q'] ?? ''));
$verTodos = isset($_GET['ver_todos']);

$condiciones = ["n.estado != 'publicado'", "n.estado != 'rechazado'"];
$params = [];

if (!adminEsAdministrador() && !$verTodos) {
    $condiciones[] = 'n.admin_asignado_id = :admin_id';
    $params[':admin_id'] = adminId();
}

if ($vista === 'pendientes' && !$filtroEstado && !$filtroResultado) {
    $condiciones[] = "(n.resultado_contacto = 'sin_contactar' OR (n.proximo_seguimiento IS NOT NULL AND n.proximo_seguimiento < CURDATE()))";
}
if ($vista === 'sin_contactar') {
    $condiciones[] = "n.resultado_contacto = 'sin_contactar'";
}
if ($vista === 'vencidos') {
    $condiciones[] = 'n.proximo_seguimiento IS NOT NULL AND n.proximo_seguimiento < CURDATE()';
}
if ($filtroEstado) {
    $condiciones[] = 'n.estado = :estado';
    $params[':estado'] = $filtroEstado;
}
if ($filtroOrigen) {
    $condiciones[] = 'n.origen = :origen';
    $params[':origen'] = $filtroOrigen;
}
if ($filtroAsignado) {
    $condiciones[] = 'n.admin_asignado_id = :asignado';
    $params[':asignado'] = $filtroAsignado;
}
if ($filtroDepartamento) {
    $condiciones[] = 'n.departamento_id = :departamento';
    $params[':departamento'] = $filtroDepartamento;
}
if ($filtroResultado) {
    $condiciones[] = 'n.resultado_contacto = :resultado';
    $params[':resultado'] = $filtroResultado;
}
if ($busqueda !== '') {
    $condiciones[] = '(n.nombre_comercial LIKE :busqueda OR n.telefono_publico LIKE :busqueda2)';
    $params[':busqueda'] = '%' . $busqueda . '%';
    $params[':busqueda2'] = '%' . $busqueda . '%';
}

$sql = 'SELECT n.id, n.nombre_comercial, n.telefono_publico, n.contacto_nombre, n.estado, n.origen,
               n.resultado_contacto, n.intentos_contacto, n.proximo_seguimiento, n.creado_en,
               n.admin_asignado_id, a.nombre AS asignado_nombre, dep.nombre AS departamento
        FROM une_negocios n
        LEFT JOIN une_admins a ON a.id = n.admin_asignado_id
        LEFT JOIN une_departamentos dep ON dep.id = n.departamento_id
        WHERE ' . implode(' AND ', $condiciones) . '
        ORDER BY (n.proximo_seguimiento IS NOT NULL AND n.proximo_seguimiento < CURDATE()) DESC,
                 n.resultado_contacto = \'sin_contactar\' DESC,
                 n.creado_en ASC
        LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$admins = $pdo->query("SELECT id, nombre FROM une_admins WHERE activo = 1 ORDER BY nombre")->fetchAll();
$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo')->fetchAll();

$tituloPagina = 'Bandeja de leads — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <h1>Bandeja de leads</h1>

  <?php if ($mensaje): ?><p class="alerta alerta--exito"><?= e($mensaje) ?></p><?php endif; ?>

  <div class="filtros-rapidos">
    <a href="?vista=pendientes" class="<?= $vista === 'pendientes' ? 'activo' : '' ?>">Pendientes de hoy</a>
    <a href="?vista=sin_contactar" class="<?= $vista === 'sin_contactar' ? 'activo' : '' ?>">Sin contactar</a>
    <a href="?vista=vencidos" class="<?= $vista === 'vencidos' ? 'activo' : '' ?>">Seguimiento vencido</a>
    <a href="?vista=todos">Todos</a>
    <?php if (!adminEsAdministrador()): ?>
      <a href="?ver_todos=1&vista=<?= e($vista) ?>" class="filtro-ver-todos"><?= $verTodos ? 'Ver solo mis leads' : 'Ver todos los leads' ?></a>
    <?php endif; ?>
  </div>

  <form method="get" class="formulario-filtros">
    <input type="hidden" name="vista" value="<?= e($vista) ?>">
    <input type="search" name="q" placeholder="Buscar por nombre o teléfono" value="<?= e($busqueda) ?>">
    <select name="estado">
      <option value="">Todos los estados</option>
      <?php foreach (['lead', 'en_gestion', 'en_revision', 'no_contactable', 'duplicado'] as $est): ?>
        <option value="<?= e($est) ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $est))) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="origen">
      <option value="">Todos los orígenes</option>
      <?php foreach (['captura_rapida', 'sugerencia', 'importacion', 'alta_admin', 'reclamo'] as $ori): ?>
        <option value="<?= e($ori) ?>" <?= $filtroOrigen === $ori ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $ori)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="departamento_id">
      <option value="">Todos los departamentos</option>
      <?php foreach ($departamentos as $d): ?>
        <option value="<?= (int) $d['id'] ?>" <?= (string) $filtroDepartamento === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (adminEsAdministrador()): ?>
      <select name="admin_asignado_id">
        <option value="">Todos los responsables</option>
        <?php foreach ($admins as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= (string) $filtroAsignado === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <button type="submit" class="boton boton--secundario">Filtrar</button>
  </form>

  <form method="post" id="formulario-lote">
    <?= csrfCampo() ?>
    <input type="hidden" name="accion" id="accion-lote" value="">

    <?php if (adminEsAdministrador()): ?>
    <div class="barra-acciones-lote">
      <select name="admin_asignado_id">
        <option value="">Asignar a…</option>
        <?php foreach ($admins as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['nombre']) ?></option><?php endforeach; ?>
      </select>
      <button type="submit" onclick="document.getElementById('accion-lote').value='lote_asignar'">Asignar seleccionados</button>

      <select name="nuevo_estado">
        <option value="en_gestion">En gestión</option>
        <option value="no_contactable">No contactable</option>
        <option value="duplicado">Duplicado</option>
        <option value="rechazado">Rechazado</option>
      </select>
      <button type="submit" onclick="document.getElementById('accion-lote').value='lote_estado'">Cambiar estado seleccionados</button>
    </div>
    <?php endif; ?>

    <div class="tabla-responsiva">
    <table class="tabla-admin tabla-leads">
      <thead>
        <tr>
          <th><input type="checkbox" id="marcar-todos"></th>
          <th>Negocio</th>
          <th>Teléfono</th>
          <th>Representante</th>
          <th>Captado</th>
          <th>Estado</th>
          <th>Resultado</th>
          <th>Intentos</th>
          <th>Responsable</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <?php
            $vencido = $lead['proximo_seguimiento'] && $lead['proximo_seguimiento'] < date('Y-m-d');
            $mensajePlantilla = "Hola {$lead['contacto_nombre']}, le escribo de " . SITE_NAME . ". Estamos armando el directorio nacional de academias formativas y queremos publicar la ficha de {$lead['nombre_comercial']} sin costo. ¿Le puedo hacer unas preguntas?";
          ?>
          <tr class="<?= $vencido ? 'fila-vencida' : '' ?>">
            <td><input type="checkbox" name="negocio_ids[]" value="<?= (int) $lead['id'] ?>" form="formulario-lote"></td>
            <td><a href="/admin/negocio-editar.php?id=<?= (int) $lead['id'] ?>"><?= e($lead['nombre_comercial']) ?></a><br><small><?= e($lead['departamento'] ?? '') ?></small></td>
            <td>
              <?php if ($lead['telefono_publico']): ?>
                <a href="tel:+51<?= e($lead['telefono_publico']) ?>">📞 <?= e($lead['telefono_publico']) ?></a><br>
                <a href="<?= e(enlaceWhatsApp($lead['telefono_publico'], $mensajePlantilla)) ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
              <?php endif; ?>
            </td>
            <td><?= e($lead['contacto_nombre'] ?? '') ?></td>
            <td><?= e(tiempoRelativo($lead['creado_en'])) ?></td>
            <td><?= e(str_replace('_', ' ', $lead['estado'])) ?></td>
            <td><?= e(str_replace('_', ' ', $lead['resultado_contacto'])) ?></td>
            <td><?= (int) $lead['intentos_contacto'] ?></td>
            <td><?= e($lead['asignado_nombre'] ?? '—') ?></td>
            <td>
              <details class="registrar-contacto">
                <summary>Registrar contacto</summary>
                <form method="post" class="formulario-contacto-rapido">
                  <?= csrfCampo() ?>
                  <input type="hidden" name="accion" value="registrar_contacto">
                  <input type="hidden" name="negocio_id" value="<?= (int) $lead['id'] ?>">
                  <select name="tipo_contacto">
                    <option value="llamada">Llamada</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="correo">Correo</option>
                    <option value="visita">Visita</option>
                  </select>
                  <select name="resultado_contacto" required>
                    <option value="no_contesta">No contesta</option>
                    <option value="numero_errado">Número errado</option>
                    <option value="interesado">Interesado</option>
                    <option value="en_espera">En espera</option>
                    <option value="rechazo">Rechazo</option>
                  </select>
                  <label>Próximo seguimiento <input type="date" name="proximo_seguimiento"></label>
                  <textarea name="nota" placeholder="Nota (opcional)" rows="2"></textarea>
                  <button type="submit" class="boton boton--primario boton--pequeno">Guardar</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$leads): ?>
          <tr><td colspan="10">No hay leads que coincidan con este filtro.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </form>
</div>
<script src="/assets/js/admin.js" defer></script>
<?php
require __DIR__ . '/../includes/admin-footer.php';
