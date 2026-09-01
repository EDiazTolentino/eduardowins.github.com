<?php
/**
 * admin/eventos.php — Cartelera de eventos deportivos nacionales e
 * internacionales (flyer + nota corta + enlace del organizador).
 * UNE Sports no organiza estos eventos, solo los difunde.
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
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $ambito = ($_POST['ambito'] ?? '') === 'internacional' ? 'internacional' : 'nacional';
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $nota = trim((string) ($_POST['nota'] ?? '')) ?: null;
        $enlaceOrganizador = trim((string) ($_POST['enlace_organizador'] ?? '')) ?: null;
        $departamentoId = $ambito === 'nacional' ? ((int) ($_POST['departamento_id'] ?? 0) ?: null) : null;
        $fechaEvento = trim((string) ($_POST['fecha_evento'] ?? '')) ?: null;
        $publicado = !empty($_POST['publicado']) ? 1 : 0;

        $flyerArchivo = null;
        if (!empty($_FILES['flyer']['name'])) {
            $resultado = subirImagen($_FILES['flyer'], 'galeria', 1200, 3);
            if ($resultado['ok']) {
                $flyerArchivo = $resultado['archivo'];
            } else {
                $mensaje = $resultado['error'];
            }
        }

        if ($titulo === '') {
            $mensaje = 'El título es obligatorio.';
        } elseif ($id > 0) {
            $sql = 'UPDATE une_eventos_deportivos SET ambito=:ambito, titulo=:titulo, nota=:nota,
                    enlace_organizador=:enlace_organizador, departamento_id=:departamento_id,
                    fecha_evento=:fecha_evento, publicado=:publicado';
            $params = [
                ':ambito' => $ambito, ':titulo' => $titulo, ':nota' => $nota,
                ':enlace_organizador' => $enlaceOrganizador, ':departamento_id' => $departamentoId,
                ':fecha_evento' => $fechaEvento, ':publicado' => $publicado, ':id' => $id,
            ];
            if ($flyerArchivo) {
                $sql .= ', flyer=:flyer';
                $params[':flyer'] = $flyerArchivo;
            }
            $sql .= ' WHERE id=:id';
            $pdo->prepare($sql)->execute($params);
            $mensaje = 'Evento actualizado.';
        } else {
            $pdo->prepare(
                'INSERT INTO une_eventos_deportivos
                    (ambito, titulo, flyer, nota, enlace_organizador, departamento_id, fecha_evento, publicado)
                 VALUES
                    (:ambito, :titulo, :flyer, :nota, :enlace_organizador, :departamento_id, :fecha_evento, :publicado)'
            )->execute([
                ':ambito' => $ambito, ':titulo' => $titulo, ':flyer' => $flyerArchivo, ':nota' => $nota,
                ':enlace_organizador' => $enlaceOrganizador, ':departamento_id' => $departamentoId,
                ':fecha_evento' => $fechaEvento, ':publicado' => $publicado,
            ]);
            $mensaje = 'Evento creado.';
            $id = (int) $pdo->lastInsertId();
        }
        header('Location: /admin/eventos.php?id=' . $id . '&guardado=1');
        exit;
    }

    if ($accion === 'eliminar') {
        exigirRolAdministrador();
        $eliminarId = (int) $_POST['id'];
        $stmtFlyer = $pdo->prepare('SELECT flyer FROM une_eventos_deportivos WHERE id = :id');
        $stmtFlyer->execute([':id' => $eliminarId]);
        $flyer = $stmtFlyer->fetchColumn();
        if ($flyer) {
            @unlink(RUTA_UPLOADS . '/galeria/' . $flyer);
        }
        $pdo->prepare('DELETE FROM une_eventos_deportivos WHERE id = :id')->execute([':id' => $eliminarId]);
        header('Location: /admin/eventos.php?eliminado=1');
        exit;
    }
}

$idEditar = (int) ($_GET['id'] ?? 0);
$evento = null;
if ($idEditar) {
    $stmt = $pdo->prepare('SELECT * FROM une_eventos_deportivos WHERE id = :id');
    $stmt->execute([':id' => $idEditar]);
    $evento = $stmt->fetch();
}

$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo')->fetchAll();

$eventos = $pdo->query(
    'SELECT e.id, e.ambito, e.titulo, e.fecha_evento, e.publicado, dep.nombre AS departamento
     FROM une_eventos_deportivos e
     LEFT JOIN une_departamentos dep ON dep.id = e.departamento_id
     ORDER BY e.fecha_evento DESC'
)->fetchAll();

$tituloPagina = 'Eventos — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <h1>Eventos deportivos (nacionales e internacionales)</h1>
  <p class="texto-ayuda">Cartelera de difusión: flyer, nota corta y enlace del organizador. UNE Sports no organiza estos eventos.</p>
  <?php if (isset($_GET['guardado'])): ?><p class="alerta alerta--exito">Guardado.</p><?php endif; ?>
  <?php if (isset($_GET['eliminado'])): ?><p class="alerta alerta--exito">Evento eliminado.</p><?php endif; ?>
  <?php if ($mensaje): ?><p class="alerta alerta--error"><?= e($mensaje) ?></p><?php endif; ?>

  <div class="editor-negocio__layout">
    <div class="editor-negocio__principal">
      <h2><?= $evento ? 'Editar evento' : 'Nuevo evento' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($evento['id'] ?? 0) ?>">

        <div class="campo">
          <label for="ambito">Ámbito *</label>
          <select id="ambito" name="ambito" required onchange="document.getElementById('campo-departamento').hidden = (this.value !== 'nacional');">
            <option value="nacional" <?= ($evento['ambito'] ?? 'nacional') === 'nacional' ? 'selected' : '' ?>>Nacional</option>
            <option value="internacional" <?= ($evento['ambito'] ?? '') === 'internacional' ? 'selected' : '' ?>>Internacional</option>
          </select>
        </div>

        <div class="campo" id="campo-departamento" <?= ($evento['ambito'] ?? 'nacional') === 'internacional' ? 'hidden' : '' ?>>
          <label for="departamento_id">Región (opcional)</label>
          <select id="departamento_id" name="departamento_id">
            <option value="">Sin región específica</option>
            <?php foreach ($departamentos as $dep): ?>
              <option value="<?= (int) $dep['id'] ?>" <?= (int) ($evento['departamento_id'] ?? 0) === (int) $dep['id'] ? 'selected' : '' ?>><?= e($dep['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo"><label for="titulo">Título *</label><input type="text" id="titulo" name="titulo" required value="<?= e($evento['titulo'] ?? '') ?>"></div>
        <div class="campo"><label for="nota">Nota de prensa (corta)</label><textarea id="nota" name="nota" rows="4"><?= e($evento['nota'] ?? '') ?></textarea></div>
        <div class="campo"><label for="enlace_organizador">Enlace del organizador</label><input type="url" id="enlace_organizador" name="enlace_organizador" placeholder="https://..." value="<?= e($evento['enlace_organizador'] ?? '') ?>"></div>
        <div class="campo"><label for="fecha_evento">Fecha del evento</label><input type="date" id="fecha_evento" name="fecha_evento" value="<?= e($evento['fecha_evento'] ?? '') ?>"></div>
        <div class="campo">
          <label for="flyer">Flyer</label>
          <?php if (!empty($evento['flyer'])): ?><img src="/uploads/galeria/<?= e($evento['flyer']) ?>" width="160" alt=""><?php endif; ?>
          <input type="file" id="flyer" name="flyer" accept="image/png,image/jpeg,image/webp">
        </div>
        <label class="opcion-casilla"><input type="checkbox" name="publicado" <?= !empty($evento['publicado']) ? 'checked' : '' ?>> Publicado</label>

        <button type="submit" class="boton boton--primario">Guardar</button>
        <?php if ($evento): ?><a href="/admin/eventos.php" class="boton boton--secundario">Nuevo evento</a><?php endif; ?>
      </form>
    </div>

    <aside class="editor-negocio__lateral">
      <div class="panel-lead">
        <h2>Eventos cargados</h2>
        <?php if (!$eventos): ?>
          <p class="texto-ayuda">Todavía no hay ninguno.</p>
        <?php else: ?>
          <ul class="historial-lead">
            <?php foreach ($eventos as $ev): ?>
              <li>
                <a href="/admin/eventos.php?id=<?= (int) $ev['id'] ?>"><?= e($ev['titulo']) ?></a>
                — <?= $ev['ambito'] === 'nacional' ? 'Nacional' : 'Internacional' ?><?= $ev['departamento'] ? ' (' . e($ev['departamento']) . ')' : '' ?>
                <?= $ev['fecha_evento'] ? ' · ' . e(date('d/m/Y', strtotime($ev['fecha_evento']))) : '' ?>
                <?= $ev['publicado'] ? '' : ' · borrador' ?>
                <?php if (adminEsAdministrador()): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este evento?');">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                    <button type="submit" class="boton-enlace boton-enlace--peligro">Eliminar</button>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>
<?php
require __DIR__ . '/../includes/admin-footer.php';
