<?php
/**
 * admin/equipo.php — Gestión del equipo mostrado en pages/nosotros.php
 * (listado + alta/edición + eliminación). La tabla une_equipo ya existía
 * desde la Fase 1; solo faltaba esta pantalla para administrarla sin
 * pasar por phpMyAdmin.
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
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $cargo = trim((string) ($_POST['cargo'] ?? '')) ?: null;
        $bio = trim((string) ($_POST['bio'] ?? '')) ?: null;
        $linkedin = trim((string) ($_POST['linkedin'] ?? '')) ?: null;
        $orden = (int) ($_POST['orden'] ?? 0);

        $fotoArchivo = null;
        if (!empty($_FILES['foto']['name'])) {
            $resultado = subirImagen($_FILES['foto'], 'logos', 480, 3);
            if ($resultado['ok']) {
                $fotoArchivo = $resultado['archivo'];
            } else {
                $mensaje = $resultado['error'];
            }
        }

        if ($nombre === '') {
            $mensaje = 'El nombre es obligatorio.';
        } elseif ($id > 0) {
            $sql = 'UPDATE une_equipo SET nombre=:nombre, cargo=:cargo, bio=:bio, linkedin=:linkedin, orden=:orden';
            $params = [
                ':nombre' => $nombre, ':cargo' => $cargo, ':bio' => $bio,
                ':linkedin' => $linkedin, ':orden' => $orden, ':id' => $id,
            ];
            if ($fotoArchivo) {
                $sql .= ', foto=:foto';
                $params[':foto'] = $fotoArchivo;
            }
            $sql .= ' WHERE id=:id';
            $pdo->prepare($sql)->execute($params);
            $mensaje = 'Integrante actualizado.';
        } else {
            $pdo->prepare(
                'INSERT INTO une_equipo (nombre, cargo, bio, foto, linkedin, orden)
                 VALUES (:nombre, :cargo, :bio, :foto, :linkedin, :orden)'
            )->execute([
                ':nombre' => $nombre, ':cargo' => $cargo, ':bio' => $bio,
                ':foto' => $fotoArchivo, ':linkedin' => $linkedin, ':orden' => $orden,
            ]);
            $mensaje = 'Integrante agregado.';
            $id = (int) $pdo->lastInsertId();
        }
        header('Location: /admin/equipo.php?id=' . $id . '&guardado=1');
        exit;
    }

    if ($accion === 'eliminar') {
        exigirRolAdministrador();
        $eliminarId = (int) $_POST['id'];
        $stmtFoto = $pdo->prepare('SELECT foto FROM une_equipo WHERE id = :id');
        $stmtFoto->execute([':id' => $eliminarId]);
        $foto = $stmtFoto->fetchColumn();
        if ($foto) {
            @unlink(RUTA_UPLOADS . '/logos/' . $foto);
        }
        $pdo->prepare('DELETE FROM une_equipo WHERE id = :id')->execute([':id' => $eliminarId]);
        header('Location: /admin/equipo.php?eliminado=1');
        exit;
    }
}

$idEditar = (int) ($_GET['id'] ?? 0);
$persona = null;
if ($idEditar) {
    $stmt = $pdo->prepare('SELECT * FROM une_equipo WHERE id = :id');
    $stmt->execute([':id' => $idEditar]);
    $persona = $stmt->fetch();
}

$equipo = $pdo->query('SELECT id, nombre, cargo, orden FROM une_equipo ORDER BY orden, nombre')->fetchAll();

$tituloPagina = 'Equipo — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <h1>Equipo (página Nosotros)</h1>
  <p class="texto-ayuda">Las personas que agregues aquí aparecen en la sección "Nuestro equipo" de <a href="/nosotros" target="_blank">/nosotros</a>. Si no hay ninguna, esa sección simplemente no se muestra.</p>
  <?php if (isset($_GET['guardado'])): ?><p class="alerta alerta--exito">Guardado.</p><?php endif; ?>
  <?php if (isset($_GET['eliminado'])): ?><p class="alerta alerta--exito">Integrante eliminado.</p><?php endif; ?>
  <?php if ($mensaje): ?><p class="alerta alerta--error"><?= e($mensaje) ?></p><?php endif; ?>

  <div class="editor-negocio__layout">
    <div class="editor-negocio__principal">
      <h2><?= $persona ? 'Editar integrante' : 'Nuevo integrante' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($persona['id'] ?? 0) ?>">

        <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" required value="<?= e($persona['nombre'] ?? '') ?>"></div>
        <div class="campo"><label for="cargo">Cargo</label><input type="text" id="cargo" name="cargo" placeholder="Ej. Fundadora y directora" value="<?= e($persona['cargo'] ?? '') ?>"></div>
        <div class="campo"><label for="bio">Bio corta</label><textarea id="bio" name="bio" rows="3"><?= e($persona['bio'] ?? '') ?></textarea></div>
        <div class="campo">
          <label for="foto">Foto</label>
          <?php if (!empty($persona['foto'])): ?><img src="/uploads/logos/<?= e($persona['foto']) ?>" width="100" alt=""><?php endif; ?>
          <input type="file" id="foto" name="foto" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="campo"><label for="linkedin">LinkedIn (opcional)</label><input type="url" id="linkedin" name="linkedin" placeholder="https://www.linkedin.com/in/..." value="<?= e($persona['linkedin'] ?? '') ?>"></div>
        <div class="campo"><label for="orden">Orden</label><input type="number" id="orden" name="orden" min="0" value="<?= (int) ($persona['orden'] ?? 0) ?>"></div>
        <p class="texto-ayuda">El "orden" define en qué posición aparece (0 primero). Si dos personas tienen el mismo número, se ordenan por nombre.</p>

        <button type="submit" class="boton boton--primario">Guardar</button>
        <?php if ($persona): ?><a href="/admin/equipo.php" class="boton boton--secundario">Nuevo integrante</a><?php endif; ?>
      </form>
    </div>

    <aside class="editor-negocio__lateral">
      <div class="panel-lead">
        <h2>Equipo actual</h2>
        <?php if (!$equipo): ?>
          <p class="texto-ayuda">Todavía no hay nadie cargado.</p>
        <?php else: ?>
          <ul class="historial-lead">
            <?php foreach ($equipo as $p): ?>
              <li>
                <a href="/admin/equipo.php?id=<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></a>
                <?php if ($p['cargo']): ?> — <?= e($p['cargo']) ?><?php endif; ?>
                <?php if (adminEsAdministrador()): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar a esta persona del equipo?');">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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
