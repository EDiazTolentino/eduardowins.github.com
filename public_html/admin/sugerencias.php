<?php
/**
 * admin/sugerencias.php — Bandeja de sugerencias ciudadanas (§6 vía 3)
 * y de reclamos de ficha (§6 vía 5). No hay pantallas separadas para
 * cada una en el prompt original, así que se combinan aquí: ambas son
 * "solicitudes externas que el equipo debe procesar".
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

    if ($accion === 'convertir_sugerencia') {
        $id = (int) $_POST['id'];
        $stmtSug = $pdo->prepare('SELECT * FROM une_sugerencias WHERE id = :id');
        $stmtSug->execute([':id' => $id]);
        $sug = $stmtSug->fetch();
        if ($sug && !$sug['procesada']) {
            $slug = generarSlugUnico($pdo, $sug['nombre_lugar']);
            $telefono = null;
            if ($sug['contacto_dato']) {
                $normalizado = normalizarTelefono($sug['contacto_dato']);
                if ($normalizado && validarTelefonoPeru($normalizado)) {
                    $telefono = $normalizado;
                }
            }
            $notas = 'Creado a partir de una sugerencia ciudadana.';
            if ($sug['contacto_dato'] && !$telefono) {
                $notas .= ' Dato de contacto original: ' . $sug['contacto_dato'];
            }
            if ($sug['comentario']) {
                $notas .= ' | ' . $sug['comentario'];
            }

            $pdo->prepare(
                "INSERT INTO une_negocios (slug, tipo_registro, nombre_comercial, departamento_id, provincia_id, distrito_id, telefono_publico, estado, origen, notas_internas, token_edicion)
                 VALUES (:slug, 'formativo', :nombre, :dep, :prov, :dist, :tel, 'lead', 'sugerencia', :notas, :token)"
            )->execute([
                ':slug' => $slug, ':nombre' => $sug['nombre_lugar'],
                ':dep' => DEPARTAMENTO_SIN_DEFINIR_ID, ':prov' => null, ':dist' => $sug['distrito_id'],
                ':tel' => $telefono, ':notas' => $notas, ':token' => generarTokenEdicion(),
            ]);
            $negocioId = (int) $pdo->lastInsertId();

            if ($sug['deporte_id']) {
                $pdo->prepare('INSERT IGNORE INTO une_negocio_deportes (negocio_id, deporte_id) VALUES (:id, :dep)')
                    ->execute([':id' => $negocioId, ':dep' => $sug['deporte_id']]);
            }
            $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion, nota) VALUES (:id, :admin, \'creado\', \'Convertido desde una sugerencia ciudadana.\')')
                ->execute([':id' => $negocioId, ':admin' => adminId()]);
            $pdo->prepare('UPDATE une_sugerencias SET procesada = 1, negocio_id = :negocio WHERE id = :id')
                ->execute([':negocio' => $negocioId, ':id' => $id]);

            $mensaje = 'Sugerencia convertida en lead.';
        }
    }

    if ($accion === 'descartar_sugerencia') {
        $pdo->prepare('UPDATE une_sugerencias SET procesada = 1 WHERE id = :id')->execute([':id' => (int) $_POST['id']]);
        $mensaje = 'Sugerencia descartada.';
    }

    if ($accion === 'aprobar_reclamo') {
        $id = (int) $_POST['id'];
        $stmtRec = $pdo->prepare('SELECT r.*, n.nombre_comercial, n.token_edicion FROM une_reclamos r JOIN une_negocios n ON n.id = r.negocio_id WHERE r.id = :id');
        $stmtRec->execute([':id' => $id]);
        $reclamo = $stmtRec->fetch();
        if ($reclamo) {
            $token = $reclamo['token_edicion'];
            if (!$token) {
                $token = generarTokenEdicion();
                $pdo->prepare('UPDATE une_negocios SET token_edicion = :token WHERE id = :id')->execute([':token' => $token, ':id' => $reclamo['negocio_id']]);
            }
            $pdo->prepare("UPDATE une_reclamos SET estado = 'aprobado' WHERE id = :id")->execute([':id' => $id]);
            $enlaceEdicion = SITE_URL . '/editar/' . $token;
            if ($reclamo['correo']) {
                enviarCorreoSimple(
                    $reclamo['correo'],
                    'Tu enlace para editar ' . $reclamo['nombre_comercial'],
                    "<p>Hola {$reclamo['nombre']},</p><p>Confirmamos que puedes editar la ficha de <strong>{$reclamo['nombre_comercial']}</strong> desde este enlace:</p><p><a href=\"{$enlaceEdicion}\">{$enlaceEdicion}</a></p>"
                );
            }
            $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion, nota) VALUES (:id, :admin, \'cambio_estado\', :nota)')
                ->execute([':id' => $reclamo['negocio_id'], ':admin' => adminId(), ':nota' => 'Reclamo aprobado. Enlace de edición: ' . $enlaceEdicion]);
            $mensaje = 'Reclamo aprobado. Enlace de edición: ' . $enlaceEdicion;
        }
    }

    if ($accion === 'rechazar_reclamo') {
        $pdo->prepare("UPDATE une_reclamos SET estado = 'rechazado' WHERE id = :id")->execute([':id' => (int) $_POST['id']]);
        $mensaje = 'Reclamo rechazado.';
    }
}

$sugerencias = $pdo->query(
    "SELECT s.*, d.nombre AS distrito, dep.nombre AS deporte
     FROM une_sugerencias s
     LEFT JOIN une_distritos d ON d.id = s.distrito_id
     LEFT JOIN une_deportes dep ON dep.id = s.deporte_id
     WHERE s.procesada = 0 ORDER BY s.creado_en ASC"
)->fetchAll();

$reclamos = $pdo->query(
    "SELECT r.*, n.nombre_comercial, n.slug
     FROM une_reclamos r JOIN une_negocios n ON n.id = r.negocio_id
     WHERE r.estado = 'pendiente' ORDER BY r.creado_en ASC"
)->fetchAll();

$tituloPagina = 'Sugerencias y reclamos — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <h1>Sugerencias y reclamos</h1>
  <?php if ($mensaje): ?><p class="alerta alerta--exito"><?= e($mensaje) ?></p><?php endif; ?>

  <h2>Sugerencias ciudadanas pendientes (<?= count($sugerencias) ?>)</h2>
  <div class="tabla-responsiva">
  <table class="tabla-admin">
    <thead><tr><th>Lugar</th><th>Distrito</th><th>Deporte</th><th>Contacto</th><th>Comentario</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($sugerencias as $s): ?>
        <tr>
          <td><?= e($s['nombre_lugar']) ?></td>
          <td><?= e($s['distrito'] ?? '') ?></td>
          <td><?= e($s['deporte'] ?? '') ?></td>
          <td><?= e($s['contacto_dato'] ?? '') ?></td>
          <td><?= e($s['comentario'] ?? '') ?></td>
          <td>
            <form method="post" style="display:inline">
              <?= csrfCampo() ?>
              <input type="hidden" name="accion" value="convertir_sugerencia">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button type="submit" class="boton-enlace">Convertir en lead</button>
            </form>
            ·
            <form method="post" style="display:inline">
              <?= csrfCampo() ?>
              <input type="hidden" name="accion" value="descartar_sugerencia">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button type="submit" class="boton-enlace boton-enlace--peligro">Descartar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sugerencias): ?><tr><td colspan="6">No hay sugerencias pendientes.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

  <h2>Reclamos de ficha pendientes (<?= count($reclamos) ?>)</h2>
  <div class="tabla-responsiva">
  <table class="tabla-admin">
    <thead><tr><th>Ficha</th><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($reclamos as $r): ?>
        <tr>
          <td><a href="/admin/negocio-editar.php?id=<?= (int) $r['negocio_id'] ?>"><?= e($r['nombre_comercial']) ?></a></td>
          <td><?= e($r['nombre']) ?></td>
          <td><a href="tel:+51<?= e($r['telefono']) ?>"><?= e($r['telefono']) ?></a></td>
          <td><?= e($r['correo'] ?? '—') ?></td>
          <td>
            <form method="post" style="display:inline">
              <?= csrfCampo() ?>
              <input type="hidden" name="accion" value="aprobar_reclamo">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="boton-enlace">Aprobar</button>
            </form>
            ·
            <form method="post" style="display:inline">
              <?= csrfCampo() ?>
              <input type="hidden" name="accion" value="rechazar_reclamo">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="boton-enlace boton-enlace--peligro">Rechazar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reclamos): ?><tr><td colspan="5">No hay reclamos pendientes.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php
require __DIR__ . '/../includes/admin-footer.php';
