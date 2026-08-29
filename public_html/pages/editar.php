<?php
/**
 * pages/editar.php — Edición vía token, sin login (§6 vía 5, §10).
 * $token llega desde index.php. Cualquier cambio enviado por esta vía
 * entra como estado='en_revision' hasta que un administrador lo aprueba
 * — nunca se publica directamente ni pisa una verificación existente.
 */

$stmt = $pdo->prepare('SELECT * FROM une_negocios WHERE token_edicion = :token');
$stmt->execute([':token' => $token]);
$negocio = $stmt->fetch();

if (!$negocio) {
    http_response_code(404);
    $tituloPagina = 'Enlace no válido — ' . SITE_NAME;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="contenedor seccion-error-404"><h1>Este enlace de edición no es válido</h1><p>Puede haber expirado o estar mal copiado. Escríbenos a ' . e(SITE_EMAIL_CONTACTO) . ' si necesitas uno nuevo.</p></section>';
    require __DIR__ . '/../includes/footer.php';
    return;
}

$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();

    $camposTexto = [
        'nombre_comercial', 'razon_social', 'descripcion', 'direccion', 'referencia',
        'telefono_publico', 'telefono_publico_2', 'email_publico', 'web',
        'facebook', 'instagram', 'tiktok', 'youtube',
        'contacto_nombre', 'contacto_telefono', 'contacto_email', 'afiliacion_federacion',
    ];
    $camposNumericos = ['anio_fundacion', 'departamento_id', 'provincia_id', 'distrito_id', 'latitud', 'longitud', 'rango_precio', 'capacidad_alumnos', 'num_entrenadores'];
    $camposBooleanos = ['tiene_matricula', 'ofrece_beca', 'clase_prueba_gratis', 'local_propio', 'seguro_accidentes', 'protocolo_salvaguarda', 'personal_certificado', 'requiere_examen_medico'];
    $camposEnum = ['modalidad' => ['presencial', 'virtual', 'mixta'], 'atiende_genero' => ['mixto', 'femenino', 'masculino']];

    $set = ["estado = 'en_revision'"];
    $params = [':id' => $negocio['id']];
    foreach ($camposTexto as $c) {
        $set[] = "`$c` = :$c";
        $v = trim((string) ($_POST[$c] ?? ''));
        $params[":$c"] = $v === '' ? null : $v;
    }
    foreach ($camposNumericos as $c) {
        $set[] = "`$c` = :$c";
        $v = $_POST[$c] ?? '';
        $params[":$c"] = $v === '' ? null : $v;
    }
    foreach ($camposBooleanos as $c) {
        $set[] = "`$c` = :$c";
        $params[":$c"] = !empty($_POST[$c]) ? 1 : 0;
    }
    foreach ($camposEnum as $c => $valores) {
        if (in_array($_POST[$c] ?? '', $valores, true)) {
            $set[] = "`$c` = :$c";
            $params[":$c"] = $_POST[$c];
        }
    }
    if (empty($params[':departamento_id'])) {
        $params[':departamento_id'] = DEPARTAMENTO_SIN_DEFINIR_ID;
    }

    $pdo->prepare('UPDATE une_negocios SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);

    $pdo->prepare('DELETE FROM une_negocio_categorias WHERE negocio_id = :id')->execute([':id' => $negocio['id']]);
    foreach (array_unique(array_map('intval', $_POST['categorias'] ?? [])) as $catId) {
        $pdo->prepare('INSERT IGNORE INTO une_negocio_categorias (negocio_id, categoria_id) VALUES (:id, :cat)')->execute([':id' => $negocio['id'], ':cat' => $catId]);
    }

    $deporteIds = array_slice(array_unique(array_map('intval', $_POST['deportes'] ?? [])), 0, 5);
    $pdo->prepare('DELETE FROM une_negocio_deportes WHERE negocio_id = :id')->execute([':id' => $negocio['id']]);
    foreach ($deporteIds as $depId) {
        $pdo->prepare('INSERT IGNORE INTO une_negocio_deportes (negocio_id, deporte_id) VALUES (:id, :dep)')->execute([':id' => $negocio['id'], ':dep' => $depId]);
    }

    $pdo->prepare('DELETE FROM une_negocio_etapas WHERE negocio_id = :id')->execute([':id' => $negocio['id']]);
    foreach (array_unique(array_map('intval', $_POST['etapas'] ?? [])) as $etId) {
        $pdo->prepare('INSERT IGNORE INTO une_negocio_etapas (negocio_id, etapa_id) VALUES (:id, :et)')->execute([':id' => $negocio['id'], ':et' => $etId]);
    }

    $pdo->prepare('DELETE FROM une_horarios WHERE negocio_id = :id')->execute([':id' => $negocio['id']]);
    foreach ($_POST['horario_dia'] ?? [] as $i => $dia) {
        $turno = $_POST['horario_turno'][$i] ?? '';
        $inicio = $_POST['horario_inicio'][$i] ?? '';
        $fin = $_POST['horario_fin'][$i] ?? '';
        if ($dia !== '' && in_array($turno, ['mañana', 'tarde', 'noche'], true) && $inicio && $fin) {
            $pdo->prepare('INSERT INTO une_horarios (negocio_id, dia_semana, turno, hora_inicio, hora_fin) VALUES (:id, :dia, :turno, :inicio, :fin)')
                ->execute([':id' => $negocio['id'], ':dia' => (int) $dia, ':turno' => $turno, ':inicio' => $inicio, ':fin' => $fin]);
        }
    }

    if (!empty($_FILES['logo']['name'])) {
        $resultado = subirImagen($_FILES['logo'], 'logos', 800, 2);
        if ($resultado['ok']) {
            $pdo->prepare('UPDATE une_negocios SET logo = :logo WHERE id = :id')->execute([':logo' => $resultado['archivo'], ':id' => $negocio['id']]);
        }
    }
    $stmtCountImg = $pdo->prepare('SELECT COUNT(*) FROM une_imagenes WHERE negocio_id = :id');
    $stmtCountImg->execute([':id' => $negocio['id']]);
    $totalImagenes = (int) $stmtCountImg->fetchColumn();
    if (!empty($_FILES['galeria']['name'][0])) {
        foreach ($_FILES['galeria']['name'] as $i => $nombreArchivo) {
            if ($totalImagenes >= 8 || $nombreArchivo === '') {
                continue;
            }
            $archivoIndividual = ['name' => $_FILES['galeria']['name'][$i], 'type' => $_FILES['galeria']['type'][$i], 'tmp_name' => $_FILES['galeria']['tmp_name'][$i], 'error' => $_FILES['galeria']['error'][$i], 'size' => $_FILES['galeria']['size'][$i]];
            $resultado = subirImagen($archivoIndividual, 'galeria', 1200, 3);
            if ($resultado['ok']) {
                $pdo->prepare('INSERT INTO une_imagenes (negocio_id, archivo, alt, orden) VALUES (:id, :archivo, :alt, :orden)')
                    ->execute([':id' => $negocio['id'], ':archivo' => $resultado['archivo'], ':alt' => $negocio['nombre_comercial'], ':orden' => $totalImagenes]);
                $totalImagenes++;
            }
        }
    }

    calcularCompletitud($pdo, $negocio['id']);
    $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, accion, nota) VALUES (:id, \'ficha_actualizada\', \'Editado por el propio negocio vía enlace de token. Queda en revisión.\')')
        ->execute([':id' => $negocio['id']]);

    $stmt = $pdo->prepare('SELECT * FROM une_negocios WHERE id = :id');
    $stmt->execute([':id' => $negocio['id']]);
    $negocio = $stmt->fetch();
    $mensaje = 'Guardamos tus cambios. Un administrador los revisará antes de que se vean públicamente.';
}

$categorias = $pdo->query('SELECT id, nombre, tipo_registro FROM une_categorias ORDER BY tipo_registro, orden')->fetchAll();
$deportes = $pdo->query('SELECT id, nombre, grupo, icono FROM une_deportes ORDER BY orden')->fetchAll();
$etapas = $pdo->query('SELECT id, nombre, rango FROM une_etapas ORDER BY orden')->fetchAll();
$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo')->fetchAll();

$provincias = [];
if ($negocio['departamento_id']) {
    $stmtProv = $pdo->prepare('SELECT id, nombre FROM une_provincias WHERE departamento_id = :dep AND activo = 1 ORDER BY nombre');
    $stmtProv->execute([':dep' => $negocio['departamento_id']]);
    $provincias = $stmtProv->fetchAll();
}
$distritos = [];
if ($negocio['provincia_id']) {
    $stmtDist = $pdo->prepare('SELECT id, nombre FROM une_distritos WHERE provincia_id = :prov AND activo = 1 ORDER BY nombre');
    $stmtDist->execute([':prov' => $negocio['provincia_id']]);
    $distritos = $stmtDist->fetchAll();
}
$categoriasSeleccionadas = $pdo->prepare('SELECT categoria_id FROM une_negocio_categorias WHERE negocio_id = :id');
$categoriasSeleccionadas->execute([':id' => $negocio['id']]);
$categoriasSeleccionadas = $categoriasSeleccionadas->fetchAll(PDO::FETCH_COLUMN);
$deportesSeleccionados = $pdo->prepare('SELECT deporte_id FROM une_negocio_deportes WHERE negocio_id = :id');
$deportesSeleccionados->execute([':id' => $negocio['id']]);
$deportesSeleccionados = $deportesSeleccionados->fetchAll(PDO::FETCH_COLUMN);
$etapasSeleccionadas = $pdo->prepare('SELECT etapa_id FROM une_negocio_etapas WHERE negocio_id = :id');
$etapasSeleccionadas->execute([':id' => $negocio['id']]);
$etapasSeleccionadas = $etapasSeleccionadas->fetchAll(PDO::FETCH_COLUMN);
$diasSemanaNombres = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$horarios = $pdo->prepare('SELECT dia_semana, turno, hora_inicio, hora_fin FROM une_horarios WHERE negocio_id = :id ORDER BY dia_semana');
$horarios->execute([':id' => $negocio['id']]);
$horarios = $horarios->fetchAll();
$imagenes = $pdo->prepare('SELECT id, archivo, alt FROM une_imagenes WHERE negocio_id = :id ORDER BY orden');
$imagenes->execute([':id' => $negocio['id']]);
$imagenes = $imagenes->fetchAll();

$tituloPagina = 'Editar ' . $negocio['nombre_comercial'] . ' — ' . SITE_NAME;
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-editar-token">
  <h1>Editar ficha: <?= e($negocio['nombre_comercial']) ?></h1>
  <p class="texto-ayuda">Los cambios que hagas aquí se revisan antes de publicarse.</p>
  <?php if ($mensaje): ?><p class="alerta alerta--exito"><?= e($mensaje) ?></p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="formulario-editar-token">
    <?= csrfCampo() ?>

    <details open>
      <summary>Datos generales</summary>
      <div class="campo"><label for="nombre_comercial">Nombre comercial *</label><input type="text" id="nombre_comercial" name="nombre_comercial" required value="<?= e($negocio['nombre_comercial']) ?>"></div>
      <div class="campo"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4"><?= e($negocio['descripcion'] ?? '') ?></textarea></div>
      <div class="campo"><label for="razon_social">Razón social</label><input type="text" id="razon_social" name="razon_social" value="<?= e($negocio['razon_social'] ?? '') ?>"></div>
      <div class="campo"><label for="anio_fundacion">Año de fundación</label><input type="number" id="anio_fundacion" name="anio_fundacion" value="<?= e((string) ($negocio['anio_fundacion'] ?? '')) ?>"></div>
    </details>

    <details>
      <summary>Contacto</summary>
      <div class="campo"><label for="telefono_publico">Teléfono público</label><input type="text" id="telefono_publico" name="telefono_publico" value="<?= e($negocio['telefono_publico'] ?? '') ?>"></div>
      <div class="campo"><label for="telefono_publico_2">Teléfono público 2</label><input type="text" id="telefono_publico_2" name="telefono_publico_2" value="<?= e($negocio['telefono_publico_2'] ?? '') ?>"></div>
      <div class="campo"><label for="email_publico">Correo público</label><input type="email" id="email_publico" name="email_publico" value="<?= e($negocio['email_publico'] ?? '') ?>"></div>
      <div class="campo"><label for="web">Sitio web</label><input type="url" id="web" name="web" value="<?= e($negocio['web'] ?? '') ?>"></div>
      <div class="campo"><label for="facebook">Facebook</label><input type="text" id="facebook" name="facebook" value="<?= e($negocio['facebook'] ?? '') ?>"></div>
      <div class="campo"><label for="instagram">Instagram</label><input type="text" id="instagram" name="instagram" value="<?= e($negocio['instagram'] ?? '') ?>"></div>
      <div class="campo"><label for="contacto_nombre">Tu nombre (dato privado, no se publica)</label><input type="text" id="contacto_nombre" name="contacto_nombre" value="<?= e($negocio['contacto_nombre'] ?? '') ?>"></div>
      <div class="campo"><label for="contacto_telefono">Tu teléfono directo (privado)</label><input type="text" id="contacto_telefono" name="contacto_telefono" value="<?= e($negocio['contacto_telefono'] ?? '') ?>"></div>
    </details>

    <details>
      <summary>Ubicación</summary>
      <div class="campo">
        <label for="departamento_id">Departamento</label>
        <select id="departamento_id" name="departamento_id" data-ubigeo="departamento">
          <option value="">Selecciona</option>
          <?php foreach ($departamentos as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= (int) $negocio['departamento_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="provincia_id">Provincia</label>
        <select id="provincia_id" name="provincia_id">
          <option value="">Selecciona</option>
          <?php foreach ($provincias as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int) $negocio['provincia_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="distrito_id">Distrito</label>
        <select id="distrito_id" name="distrito_id">
          <option value="">Selecciona</option>
          <?php foreach ($distritos as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= (int) $negocio['distrito_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo"><label for="direccion">Dirección</label><input type="text" id="direccion" name="direccion" value="<?= e($negocio['direccion'] ?? '') ?>"></div>
      <div class="campo"><label for="referencia">Referencia</label><input type="text" id="referencia" name="referencia" value="<?= e($negocio['referencia'] ?? '') ?>"></div>
      <input type="hidden" name="latitud" value="<?= e((string) ($negocio['latitud'] ?? '')) ?>">
      <input type="hidden" name="longitud" value="<?= e((string) ($negocio['longitud'] ?? '')) ?>">
    </details>

    <details>
      <summary>Especialidad</summary>
      <div class="campo">
        <label>Categorías</label>
        <?php foreach ($categorias as $cat): ?>
          <label class="opcion-casilla"><input type="checkbox" name="categorias[]" value="<?= (int) $cat['id'] ?>" <?= in_array($cat['id'], $categoriasSeleccionadas) ? 'checked' : '' ?>> <?= e($cat['nombre']) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="campo">
        <label>Disciplinas (máximo 5)</label>
        <?php foreach ($deportes as $dep): ?>
          <label class="opcion-casilla"><input type="checkbox" name="deportes[]" value="<?= (int) $dep['id'] ?>" class="casilla-deporte" <?= in_array($dep['id'], $deportesSeleccionados) ? 'checked' : '' ?>> <?= e($dep['icono']) ?> <?= e($dep['nombre']) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="campo">
        <label>Etapas atendidas</label>
        <?php foreach ($etapas as $et): ?>
          <label class="opcion-casilla"><input type="checkbox" name="etapas[]" value="<?= (int) $et['id'] ?>" <?= in_array($et['id'], $etapasSeleccionadas) ? 'checked' : '' ?>> <?= e($et['nombre']) ?> (<?= e($et['rango']) ?>)</label>
        <?php endforeach; ?>
      </div>
    </details>

    <details>
      <summary>Horarios y precio</summary>
      <div id="lista-horarios">
        <?php foreach ($horarios as $h): ?>
          <div class="fila-horario">
            <select name="horario_dia[]">
              <?php foreach ($diasSemanaNombres as $num => $nombreDia): ?><option value="<?= $num ?>" <?= (int) $h['dia_semana'] === $num ? 'selected' : '' ?>><?= e($nombreDia) ?></option><?php endforeach; ?>
            </select>
            <select name="horario_turno[]">
              <?php foreach (['mañana', 'tarde', 'noche'] as $turno): ?><option value="<?= e($turno) ?>" <?= $h['turno'] === $turno ? 'selected' : '' ?>><?= e(ucfirst($turno)) ?></option><?php endforeach; ?>
            </select>
            <input type="time" name="horario_inicio[]" value="<?= e(substr($h['hora_inicio'], 0, 5)) ?>">
            <input type="time" name="horario_fin[]" value="<?= e(substr($h['hora_fin'], 0, 5)) ?>">
            <button type="button" class="boton-quitar-fila">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" id="agregar-horario" class="boton boton--secundario boton--pequeno">+ Agregar horario</button>
      <div class="campo">
        <label>Rango de precio</label>
        <?php for ($n = 1; $n <= 4; $n++): ?>
          <label><input type="radio" name="rango_precio" value="<?= $n ?>" <?= (int) $negocio['rango_precio'] === $n ? 'checked' : '' ?>> <?= str_repeat('S/ ', $n) ?></label>
        <?php endfor; ?>
      </div>
      <label class="opcion-casilla"><input type="checkbox" name="clase_prueba_gratis" <?= $negocio['clase_prueba_gratis'] ? 'checked' : '' ?>> Clase de prueba gratis</label>
    </details>

    <details>
      <summary>Confianza</summary>
      <label class="opcion-casilla"><input type="checkbox" name="local_propio" <?= $negocio['local_propio'] ? 'checked' : '' ?>> Local propio</label>
      <label class="opcion-casilla"><input type="checkbox" name="seguro_accidentes" <?= $negocio['seguro_accidentes'] ? 'checked' : '' ?>> Seguro contra accidentes</label>
      <label class="opcion-casilla"><input type="checkbox" name="protocolo_salvaguarda" <?= $negocio['protocolo_salvaguarda'] ? 'checked' : '' ?>> Protocolo de salvaguarda infantil</label>
      <label class="opcion-casilla"><input type="checkbox" name="personal_certificado" <?= $negocio['personal_certificado'] ? 'checked' : '' ?>> Personal certificado</label>
    </details>

    <details>
      <summary>Fotos</summary>
      <div class="campo">
        <label for="logo">Logo</label>
        <?php if ($negocio['logo']): ?><img src="/uploads/logos/<?= e($negocio['logo']) ?>" alt="Logo actual" width="80" height="80"><?php endif; ?>
        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
      </div>
      <div class="campo">
        <label>Galería (máx. 8 fotos)</label>
        <div class="galeria-actual">
          <?php foreach ($imagenes as $img): ?>
            <img src="/uploads/galeria/<?= e($img['archivo']) ?>" alt="<?= e($img['alt'] ?? '') ?>" width="100" height="75">
          <?php endforeach; ?>
        </div>
        <input type="file" name="galeria[]" accept="image/png,image/jpeg,image/webp" multiple>
      </div>
    </details>

    <button type="submit" class="boton boton--primario boton--ancho-completo">Guardar cambios</button>
  </form>
</section>
<script src="/assets/js/admin.js" defer></script>
<?php
require __DIR__ . '/../includes/footer.php';
