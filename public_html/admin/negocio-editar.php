<?php
/**
 * admin/negocio-editar.php — Ficha completa del backoffice (§7B/§7C/§7E).
 * Un solo formulario largo con secciones plegables. El autoguardado de
 * contenido vive en api/guardar-paso.php; este archivo maneja la carga
 * inicial, el bloqueo de edición concurrente, el guardado completo
 * (con archivos) y los cambios de estado (publicar/verificar).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

exigirLoginAdmin();
$pdo = BaseDatos::obtener();

const MINUTOS_BLOQUEO_EDICION = 15;

// ---------------------------------------------------------------------
// Alta de un negocio nuevo directamente desde el panel (§6, vía 4)
// ---------------------------------------------------------------------
if (isset($_GET['nuevo'])) {
    $slug = generarSlugUnico($pdo, 'nuevo-negocio-' . date('YmdHis'));
    $pdo->prepare(
        "INSERT INTO une_negocios (slug, tipo_registro, nombre_comercial, departamento_id, estado, origen, admin_asignado_id)
         VALUES (:slug, 'formativo', 'Nuevo negocio sin nombre', :sindefinir, 'lead', 'alta_admin', :admin)"
    )->execute([':slug' => $slug, ':sindefinir' => DEPARTAMENTO_SIN_DEFINIR_ID, ':admin' => adminId()]);
    $nuevoId = (int) $pdo->lastInsertId();
    header('Location: /admin/negocio-editar.php?id=' . $nuevoId);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: /admin/leads.php');
    exit;
}

// ---------------------------------------------------------------------
// Salir y liberar el bloqueo de edición
// ---------------------------------------------------------------------
if (isset($_GET['liberar'])) {
    $pdo->prepare('UPDATE une_negocios SET editando_por = NULL, editando_desde = NULL WHERE id = :id AND editando_por = :admin')
        ->execute([':id' => $id, ':admin' => adminId()]);
    header('Location: /admin/leads.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM une_negocios WHERE id = :id');
$stmt->execute([':id' => $id]);
$negocio = $stmt->fetch();
if (!$negocio) {
    header('Location: /admin/leads.php');
    exit;
}

// ---------------------------------------------------------------------
// Bloqueo de edición concurrente
// ---------------------------------------------------------------------
$bloqueadoPorOtro = false;
$nombreBloqueador = null;
$minutosBloqueado = 0;

if ($negocio['editando_por'] && (int) $negocio['editando_por'] !== adminId()) {
    $desde = strtotime($negocio['editando_desde'] ?? 'now');
    $minutosBloqueado = (int) floor((time() - $desde) / 60);
    if ($minutosBloqueado < MINUTOS_BLOQUEO_EDICION) {
        $bloqueadoPorOtro = true;
        $stmtOtro = $pdo->prepare('SELECT nombre FROM une_admins WHERE id = :id');
        $stmtOtro->execute([':id' => $negocio['editando_por']]);
        $nombreBloqueador = $stmtOtro->fetchColumn() ?: 'otro miembro del equipo';
    }
}

$modoLectura = $bloqueadoPorOtro && !isset($_GET['forzar']);

if (!$modoLectura) {
    $pdo->prepare('UPDATE une_negocios SET editando_por = :admin, editando_desde = NOW() WHERE id = :id')
        ->execute([':admin' => adminId(), ':id' => $id]);
    $negocio['editando_por'] = adminId();
}

// ---------------------------------------------------------------------
// Acciones POST
// ---------------------------------------------------------------------
$mensaje = null;
$errores = flashObtener('errores');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$modoLectura) {
    csrfExigirOMorir();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_contenido') {
        // --- Campos de texto/numéricos/booleanos/enum (igual que api/guardar-paso.php,
        //     pero aquí además se procesan los pivotes, horarios y archivos) ---
        $camposTexto = [
            'nombre_comercial', 'razon_social', 'ruc', 'descripcion',
            'direccion', 'referencia',
            'telefono_publico', 'telefono_publico_2', 'email_publico', 'web',
            'facebook', 'instagram', 'tiktok', 'youtube',
            'contacto_nombre', 'contacto_cargo', 'contacto_telefono', 'contacto_email',
            'afiliacion_federacion',
        ];
        $camposNumericos = [
            'anio_fundacion', 'departamento_id', 'provincia_id', 'distrito_id',
            'latitud', 'longitud', 'rango_precio', 'precio_mensual_ref',
            'capacidad_alumnos', 'alumnos_actuales', 'num_entrenadores',
        ];
        $camposBooleanos = [
            'tiene_matricula', 'ofrece_beca', 'clase_prueba_gratis', 'local_propio',
            'seguro_accidentes', 'protocolo_salvaguarda', 'personal_certificado',
            'requiere_examen_medico',
        ];
        $camposEnum = [
            'tipo_registro' => ['formativo', 'servicio'],
            'modalidad' => ['presencial', 'virtual', 'mixta'],
            'atiende_genero' => ['mixto', 'femenino', 'masculino'],
        ];

        $set = [];
        $params = [':id' => $id];
        foreach ($camposTexto as $c) {
            $set[] = "`$c` = :$c";
            $v = trim((string) ($_POST[$c] ?? ''));
            $params[":$c"] = $v === '' ? null : $v;
        }
        foreach ($camposNumericos as $c) {
            $set[] = "`$c` = :$c";
            $v = $_POST[$c] ?? '';
            $params[":$c"] = ($v === '' ? null : $v);
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

        // --- Categorías (máx. sin límite fijo, pero deben ser del tipo correcto) ---
        $pdo->prepare('DELETE FROM une_negocio_categorias WHERE negocio_id = :id')->execute([':id' => $id]);
        $categoriaIds = array_map('intval', $_POST['categorias'] ?? []);
        foreach (array_unique($categoriaIds) as $catId) {
            $pdo->prepare('INSERT IGNORE INTO une_negocio_categorias (negocio_id, categoria_id) VALUES (:id, :cat)')
                ->execute([':id' => $id, ':cat' => $catId]);
        }

        // --- Deportes: máximo 5 por negocio, validado en servidor ---
        $deporteIds = array_slice(array_unique(array_map('intval', $_POST['deportes'] ?? [])), 0, 5);
        $pdo->prepare('DELETE FROM une_negocio_deportes WHERE negocio_id = :id')->execute([':id' => $id]);
        foreach ($deporteIds as $depId) {
            $pdo->prepare('INSERT IGNORE INTO une_negocio_deportes (negocio_id, deporte_id) VALUES (:id, :dep)')
                ->execute([':id' => $id, ':dep' => $depId]);
        }
        if (count($_POST['deportes'] ?? []) > 5) {
            $errores[] = 'Solo se guardaron las primeras 5 disciplinas (el máximo permitido).';
        }

        // --- Etapas ---
        $etapaIds = array_map('intval', $_POST['etapas'] ?? []);
        $pdo->prepare('DELETE FROM une_negocio_etapas WHERE negocio_id = :id')->execute([':id' => $id]);
        foreach (array_unique($etapaIds) as $etId) {
            $pdo->prepare('INSERT IGNORE INTO une_negocio_etapas (negocio_id, etapa_id) VALUES (:id, :et)')
                ->execute([':id' => $id, ':et' => $etId]);
        }

        // --- Horarios: se reemplazan por completo en cada guardado ---
        $pdo->prepare('DELETE FROM une_horarios WHERE negocio_id = :id')->execute([':id' => $id]);
        $dias = $_POST['horario_dia'] ?? [];
        foreach ($dias as $i => $dia) {
            $turno = $_POST['horario_turno'][$i] ?? '';
            $inicio = $_POST['horario_inicio'][$i] ?? '';
            $fin = $_POST['horario_fin'][$i] ?? '';
            if ($dia !== '' && in_array($turno, ['mañana', 'tarde', 'noche'], true) && $inicio && $fin) {
                $pdo->prepare('INSERT INTO une_horarios (negocio_id, dia_semana, turno, hora_inicio, hora_fin) VALUES (:id, :dia, :turno, :inicio, :fin)')
                    ->execute([':id' => $id, ':dia' => (int) $dia, ':turno' => $turno, ':inicio' => $inicio, ':fin' => $fin]);
            }
        }

        // --- Logo ---
        if (!empty($_FILES['logo']['name'])) {
            $resultado = subirImagen($_FILES['logo'], 'logos', 800, 2);
            if ($resultado['ok']) {
                $pdo->prepare('UPDATE une_negocios SET logo = :logo WHERE id = :id')->execute([':logo' => $resultado['archivo'], ':id' => $id]);
            } else {
                $errores[] = 'Logo: ' . $resultado['error'];
            }
        }

        // --- Galería (hasta 8 fotos en total) ---
        $stmtCountImg = $pdo->prepare('SELECT COUNT(*) FROM une_imagenes WHERE negocio_id = :id');
        $stmtCountImg->execute([':id' => $id]);
        $totalImagenes = (int) $stmtCountImg->fetchColumn();
        if (!empty($_FILES['galeria']['name'][0])) {
            foreach ($_FILES['galeria']['name'] as $i => $nombreArchivo) {
                if ($totalImagenes >= 8) {
                    $errores[] = 'Ya alcanzaste el máximo de 8 fotos en la galería.';
                    break;
                }
                if ($nombreArchivo === '') {
                    continue;
                }
                $archivoIndividual = [
                    'name' => $_FILES['galeria']['name'][$i], 'type' => $_FILES['galeria']['type'][$i],
                    'tmp_name' => $_FILES['galeria']['tmp_name'][$i], 'error' => $_FILES['galeria']['error'][$i],
                    'size' => $_FILES['galeria']['size'][$i],
                ];
                $resultado = subirImagen($archivoIndividual, 'galeria', 1200, 3);
                if ($resultado['ok']) {
                    $pdo->prepare('INSERT INTO une_imagenes (negocio_id, archivo, alt, orden) VALUES (:id, :archivo, :alt, :orden)')
                        ->execute([':id' => $id, ':archivo' => $resultado['archivo'], ':alt' => $negocio['nombre_comercial'], ':orden' => $totalImagenes]);
                    $totalImagenes++;
                } else {
                    $errores[] = 'Foto de galería: ' . $resultado['error'];
                }
            }
        }

        // --- Eliminar fotos de galería marcadas ---
        foreach ($_POST['eliminar_imagen'] ?? [] as $imgId) {
            $stmtImg = $pdo->prepare('SELECT archivo FROM une_imagenes WHERE id = :imgid AND negocio_id = :id');
            $stmtImg->execute([':imgid' => (int) $imgId, ':id' => $id]);
            $archivo = $stmtImg->fetchColumn();
            if ($archivo) {
                @unlink(RUTA_UPLOADS . '/galeria/' . $archivo);
                $pdo->prepare('DELETE FROM une_imagenes WHERE id = :imgid')->execute([':imgid' => (int) $imgId]);
            }
        }

        calcularCompletitud($pdo, $id);
        $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion) VALUES (:id, :admin, \'ficha_actualizada\')')
            ->execute([':id' => $id, ':admin' => adminId()]);

        foreach ($errores as $error) {
            flashAgregar('errores', $error);
        }

        $siguiente = $_POST['siguiente'] ?? '';
        if ($siguiente === 'nuevo') {
            header('Location: /admin/negocio-editar.php?nuevo=1');
            exit;
        }
        if ($siguiente === 'siguiente_lead') {
            $stmtSig = $pdo->prepare(
                "SELECT id FROM une_negocios WHERE id != :id AND estado IN ('lead','en_gestion')
                 AND (admin_asignado_id = :admin OR admin_asignado_id IS NULL)
                 ORDER BY creado_en ASC LIMIT 1"
            );
            $stmtSig->execute([':id' => $id, ':admin' => adminId()]);
            $idSiguiente = $stmtSig->fetchColumn();
            if ($idSiguiente) {
                header('Location: /admin/negocio-editar.php?id=' . $idSiguiente);
                exit;
            }
        }
        header('Location: /admin/negocio-editar.php?id=' . $id . '&guardado=1');
        exit;
    }

    if ($accion === 'registrar_contacto') {
        $resultado = $_POST['resultado_contacto'] ?? 'sin_contactar';
        $proximo = $_POST['proximo_seguimiento'] ?: null;
        $nota = trim((string) ($_POST['nota'] ?? ''));
        $tipoContacto = $_POST['tipo_contacto'] ?? 'llamada';
        $resultadosValidos = ['sin_contactar', 'no_contesta', 'numero_errado', 'interesado', 'en_espera', 'rechazo'];
        if (in_array($resultado, $resultadosValidos, true)) {
            $pdo->prepare(
                "UPDATE une_negocios SET resultado_contacto = :r, intentos_contacto = intentos_contacto + 1,
                    ultimo_contacto = NOW(), proximo_seguimiento = :p, estado = IF(estado = 'lead', 'en_gestion', estado)
                 WHERE id = :id"
            )->execute([':r' => $resultado, ':p' => $proximo, ':id' => $id]);
            $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion, resultado, nota) VALUES (:id, :admin, :accion, :resultado, :nota)')
                ->execute([':id' => $id, ':admin' => adminId(), ':accion' => $tipoContacto, ':resultado' => $resultado, ':nota' => $nota ?: null]);
            $mensaje = 'Contacto registrado.';
        }
        header('Location: /admin/negocio-editar.php?id=' . $id . '&guardado=1');
        exit;
    }

    if ($accion === 'cambiar_estado') {
        $nuevoEstado = $_POST['nuevo_estado'] ?? '';

        if ($nuevoEstado === 'publicado') {
            if (!adminEsAdministrador()) {
                $nuevoEstado = 'en_revision';
                $errores[] = 'Solo un administrador puede publicar. La ficha quedó en revisión.';
            } elseif (!cumpleUmbralPublicable($pdo, $id)) {
                $errores[] = 'La ficha no cumple el umbral mínimo para publicarse (nombre, categoría/tipo, ubicación completa, teléfono y al menos una disciplina si es formativo).';
                $nuevoEstado = null;
            }
        }
        if ($nuevoEstado) {
            $pdo->prepare('UPDATE une_negocios SET estado = :estado WHERE id = :id')->execute([':estado' => $nuevoEstado, ':id' => $id]);
            $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion, resultado, nota) VALUES (:id, :admin, \'cambio_estado\', :estado, \'Cambiado desde la ficha completa.\')')
                ->execute([':id' => $id, ':admin' => adminId(), ':estado' => $nuevoEstado]);
            $mensaje = 'Estado actualizado.';
        }
    }

    if ($accion === 'verificar' && adminEsAdministrador()) {
        if (cumpleUmbralVerificada($pdo, $id)) {
            $pdo->prepare('UPDATE une_negocios SET verificado = 1, fecha_verificacion = NOW() WHERE id = :id')->execute([':id' => $id]);
            $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion, resultado, nota) VALUES (:id, :admin, \'cambio_estado\', \'verificado\', \'Ficha marcada como verificada.\')')
                ->execute([':id' => $id, ':admin' => adminId()]);
            $mensaje = 'Ficha marcada como verificada.';
        } else {
            $errores[] = 'La ficha no cumple el umbral para marcarla como verificada (etapas, descripción de 80+ caracteres, horarios y un contacto con resultado interesado/en espera).';
        }
    }

    if ($accion === 'quitar_verificacion' && adminEsAdministrador()) {
        $pdo->prepare('UPDATE une_negocios SET verificado = 0 WHERE id = :id')->execute([':id' => $id]);
        $mensaje = 'Se quitó la verificación.';
    }

    if ($accion === 'lote_asignar_individual' && adminEsAdministrador()) {
        $asignadoId = (int) ($_POST['admin_asignado_id'] ?? 0) ?: null;
        $pdo->prepare('UPDATE une_negocios SET admin_asignado_id = :admin WHERE id = :id')->execute([':admin' => $asignadoId, ':id' => $id]);
        $mensaje = 'Responsable actualizado.';
    }

    if ($accion === 'guardar_notas') {
        $pdo->prepare('UPDATE une_negocios SET notas_internas = :notas WHERE id = :id')
            ->execute([':notas' => trim((string) ($_POST['notas_internas'] ?? '')) ?: null, ':id' => $id]);
        $mensaje = 'Notas guardadas.';
    }

    // Recargar el negocio con los cambios aplicados
    $stmt = $pdo->prepare('SELECT * FROM une_negocios WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $negocio = $stmt->fetch();
}

// ---------------------------------------------------------------------
// Datos para el formulario
// ---------------------------------------------------------------------
$completitud = (int) $negocio['completitud'];
$publicable = cumpleUmbralPublicable($pdo, $id);
$verificable = cumpleUmbralVerificada($pdo, $id);

$categorias = $pdo->query('SELECT id, nombre, tipo_registro FROM une_categorias ORDER BY tipo_registro, orden')->fetchAll();
$deportes = $pdo->query('SELECT id, nombre, grupo, icono FROM une_deportes ORDER BY orden')->fetchAll();
$etapas = $pdo->query('SELECT id, nombre, rango FROM une_etapas ORDER BY orden')->fetchAll();
$departamentos = $pdo->query('SELECT id, nombre FROM une_departamentos WHERE activo = 1 ORDER BY ubigeo')->fetchAll();
$admins = $pdo->query('SELECT id, nombre FROM une_admins WHERE activo = 1 ORDER BY nombre')->fetchAll();

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

$stmtCatSel = $pdo->prepare('SELECT categoria_id FROM une_negocio_categorias WHERE negocio_id = :id');
$stmtCatSel->execute([':id' => $id]);
$categoriasSeleccionadas = $stmtCatSel->fetchAll(PDO::FETCH_COLUMN);

$stmtDepSel = $pdo->prepare('SELECT deporte_id FROM une_negocio_deportes WHERE negocio_id = :id');
$stmtDepSel->execute([':id' => $id]);
$deportesSeleccionados = $stmtDepSel->fetchAll(PDO::FETCH_COLUMN);

$stmtEtSel = $pdo->prepare('SELECT etapa_id FROM une_negocio_etapas WHERE negocio_id = :id');
$stmtEtSel->execute([':id' => $id]);
$etapasSeleccionadas = $stmtEtSel->fetchAll(PDO::FETCH_COLUMN);

$stmtHorarios = $pdo->prepare('SELECT dia_semana, turno, hora_inicio, hora_fin FROM une_horarios WHERE negocio_id = :id ORDER BY dia_semana');
$stmtHorarios->execute([':id' => $id]);
$horarios = $stmtHorarios->fetchAll();

$stmtImagenes = $pdo->prepare('SELECT id, archivo, alt FROM une_imagenes WHERE negocio_id = :id ORDER BY orden');
$stmtImagenes->execute([':id' => $id]);
$imagenes = $stmtImagenes->fetchAll();

$stmtHistorial = $pdo->prepare('SELECT h.accion, h.resultado, h.nota, h.creado_en, a.nombre AS admin_nombre
                                 FROM une_lead_historial h LEFT JOIN une_admins a ON a.id = h.admin_id
                                 WHERE h.negocio_id = :id ORDER BY h.id DESC LIMIT 15');
$stmtHistorial->execute([':id' => $id]);
$historial = $stmtHistorial->fetchAll();

$diasSemanaNombres = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$mensajeWhatsApp = "Hola " . ($negocio['contacto_nombre'] ?: '') . ", le escribo de " . SITE_NAME . ". Estamos armando el directorio nacional de academias formativas y queremos publicar la ficha de {$negocio['nombre_comercial']} sin costo. ¿Le puedo hacer unas preguntas?";

$tituloPagina = e($negocio['nombre_comercial']) . ' — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin editor-negocio">

  <?php if ($bloqueadoPorOtro): ?>
    <div class="alerta alerta--error">
      Esta ficha la está editando <strong><?= e($nombreBloqueador) ?></strong> desde hace <?= $minutosBloqueado ?> minuto(s).
      <a href="?id=<?= $id ?>&forzar=1">Forzar edición de todos modos</a>
    </div>
  <?php endif; ?>

  <?php if ($mensaje): ?><p class="alerta alerta--exito"><?= e($mensaje) ?></p><?php endif; ?>
  <?php if (isset($_GET['guardado'])): ?><p class="alerta alerta--exito">Cambios guardados.</p><?php endif; ?>
  <?php foreach ($errores as $error): ?><p class="alerta alerta--error"><?= e($error) ?></p><?php endforeach; ?>

  <div class="editor-negocio__layout">
    <div class="editor-negocio__principal">

      <div class="barra-completitud" id="barra-completitud">
        <div class="barra-completitud__pista"><div class="barra-completitud__relleno" style="width: <?= $completitud ?>%"></div></div>
        <p>Tu ficha está al <strong id="completitud-porcentaje"><?= $completitud ?></strong>%. Las fichas completas reciben 3× más consultas de padres de familia.</p>
        <p id="autoguardado-estado" class="texto-ayuda"></p>
      </div>

      <form method="post" enctype="multipart/form-data" id="formulario-negocio" data-negocio-id="<?= $id ?>" <?= $modoLectura ? 'class="solo-lectura"' : '' ?>>
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="guardar_contenido">
        <input type="hidden" name="siguiente" id="campo-siguiente" value="">
        <fieldset <?= $modoLectura ? 'disabled' : '' ?>>

        <details open>
          <summary>1. Identificación</summary>
          <div class="campo">
            <label>Tipo de registro</label>
            <label><input type="radio" name="tipo_registro" value="formativo" <?= $negocio['tipo_registro'] === 'formativo' ? 'checked' : '' ?>> Formativo</label>
            <label><input type="radio" name="tipo_registro" value="servicio" <?= $negocio['tipo_registro'] === 'servicio' ? 'checked' : '' ?>> Centro de servicio</label>
          </div>
          <div class="campo"><label for="nombre_comercial">Nombre comercial *</label><input type="text" id="nombre_comercial" name="nombre_comercial" required value="<?= e($negocio['nombre_comercial']) ?>"></div>
          <div class="campo"><label for="razon_social">Razón social</label><input type="text" id="razon_social" name="razon_social" value="<?= e($negocio['razon_social'] ?? '') ?>"></div>
          <div class="campo"><label for="ruc">RUC</label><input type="text" id="ruc" name="ruc" maxlength="11" value="<?= e($negocio['ruc'] ?? '') ?>"></div>
          <div class="campo"><label for="anio_fundacion">Año de fundación</label><input type="number" id="anio_fundacion" name="anio_fundacion" min="1900" max="<?= date('Y') ?>" value="<?= e((string) ($negocio['anio_fundacion'] ?? '')) ?>"></div>
          <div class="campo"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4"><?= e($negocio['descripcion'] ?? '') ?></textarea></div>
        </details>

        <details>
          <summary>2. Contacto privado (nunca se publica)</summary>
          <div class="campo"><label for="contacto_nombre">Representante</label><input type="text" id="contacto_nombre" name="contacto_nombre" value="<?= e($negocio['contacto_nombre'] ?? '') ?>"></div>
          <div class="campo"><label for="contacto_cargo">Cargo</label><input type="text" id="contacto_cargo" name="contacto_cargo" value="<?= e($negocio['contacto_cargo'] ?? '') ?>"></div>
          <div class="campo"><label for="contacto_telefono">Teléfono directo</label><input type="text" id="contacto_telefono" name="contacto_telefono" value="<?= e($negocio['contacto_telefono'] ?? '') ?>"></div>
          <div class="campo"><label for="contacto_email">Correo directo</label><input type="email" id="contacto_email" name="contacto_email" value="<?= e($negocio['contacto_email'] ?? '') ?>"></div>
        </details>

        <details>
          <summary>3. Contacto público</summary>
          <div class="campo"><label for="telefono_publico">Teléfono público *</label><input type="text" id="telefono_publico" name="telefono_publico" value="<?= e($negocio['telefono_publico'] ?? '') ?>"></div>
          <div class="campo"><label for="telefono_publico_2">Teléfono público 2</label><input type="text" id="telefono_publico_2" name="telefono_publico_2" value="<?= e($negocio['telefono_publico_2'] ?? '') ?>"></div>
          <div class="campo"><label for="email_publico">Correo público</label><input type="email" id="email_publico" name="email_publico" value="<?= e($negocio['email_publico'] ?? '') ?>"></div>
          <div class="campo"><label for="web">Sitio web</label><input type="url" id="web" name="web" value="<?= e($negocio['web'] ?? '') ?>"></div>
          <div class="campo"><label for="facebook">Facebook</label><input type="text" id="facebook" name="facebook" value="<?= e($negocio['facebook'] ?? '') ?>"></div>
          <div class="campo"><label for="instagram">Instagram</label><input type="text" id="instagram" name="instagram" value="<?= e($negocio['instagram'] ?? '') ?>"></div>
          <div class="campo"><label for="tiktok">TikTok</label><input type="text" id="tiktok" name="tiktok" value="<?= e($negocio['tiktok'] ?? '') ?>"></div>
          <div class="campo"><label for="youtube">YouTube</label><input type="text" id="youtube" name="youtube" value="<?= e($negocio['youtube'] ?? '') ?>"></div>
        </details>

        <details>
          <summary>4. Ubicación</summary>
          <div class="campo">
            <label for="departamento_id">Departamento *</label>
            <select id="departamento_id" name="departamento_id" data-ubigeo="departamento">
              <option value="">Selecciona</option>
              <?php foreach ($departamentos as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (int) $negocio['departamento_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="provincia_id">Provincia</label>
            <select id="provincia_id" name="provincia_id" data-ubigeo="provincia">
              <option value="">Selecciona</option>
              <?php foreach ($provincias as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) $negocio['provincia_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="distrito_id">Distrito</label>
            <select id="distrito_id" name="distrito_id" data-ubigeo="distrito">
              <option value="">Selecciona</option>
              <?php foreach ($distritos as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (int) $negocio['distrito_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo"><label for="direccion">Dirección</label><input type="text" id="direccion" name="direccion" value="<?= e($negocio['direccion'] ?? '') ?>"></div>
          <div class="campo"><label for="referencia">Referencia</label><input type="text" id="referencia" name="referencia" value="<?= e($negocio['referencia'] ?? '') ?>"></div>
          <button type="button" id="boton-geocodificar" class="boton boton--secundario">Buscar coordenadas por dirección</button>
          <div id="mapa-editor" class="mapa-editor" data-lat="<?= e((string) ($negocio['latitud'] ?? -12.05)) ?>" data-lng="<?= e((string) ($negocio['longitud'] ?? -77.03)) ?>"></div>
          <input type="hidden" id="latitud" name="latitud" value="<?= e((string) ($negocio['latitud'] ?? '')) ?>">
          <input type="hidden" id="longitud" name="longitud" value="<?= e((string) ($negocio['longitud'] ?? '')) ?>">
        </details>

        <details>
          <summary>5. Especialidad</summary>
          <div class="campo">
            <label>Categorías</label>
            <?php foreach ($categorias as $cat): ?>
              <label class="opcion-casilla" data-tipo="<?= e($cat['tipo_registro']) ?>">
                <input type="checkbox" name="categorias[]" value="<?= (int) $cat['id'] ?>" <?= in_array($cat['id'], $categoriasSeleccionadas) ? 'checked' : '' ?>>
                <?= e($cat['nombre']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="campo">
            <label>Disciplinas (máximo 5)</label>
            <div id="grupo-deportes">
              <?php foreach ($deportes as $dep): ?>
                <label class="opcion-casilla">
                  <input type="checkbox" name="deportes[]" value="<?= (int) $dep['id'] ?>" class="casilla-deporte" <?= in_array($dep['id'], $deportesSeleccionados) ? 'checked' : '' ?>>
                  <?= e($dep['icono']) ?> <?= e($dep['nombre']) ?> <small>(<?= e($dep['grupo']) ?>)</small>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="campo">
            <label>Etapas atendidas</label>
            <?php foreach ($etapas as $et): ?>
              <label class="opcion-casilla">
                <input type="checkbox" name="etapas[]" value="<?= (int) $et['id'] ?>" <?= in_array($et['id'], $etapasSeleccionadas) ? 'checked' : '' ?>>
                <?= e($et['nombre']) ?> (<?= e($et['rango']) ?>)
              </label>
            <?php endforeach; ?>
          </div>
          <div class="campo">
            <label for="atiende_genero">Atiende a</label>
            <select id="atiende_genero" name="atiende_genero">
              <option value="mixto" <?= $negocio['atiende_genero'] === 'mixto' ? 'selected' : '' ?>>Mixto</option>
              <option value="femenino" <?= $negocio['atiende_genero'] === 'femenino' ? 'selected' : '' ?>>Solo femenino</option>
              <option value="masculino" <?= $negocio['atiende_genero'] === 'masculino' ? 'selected' : '' ?>>Solo masculino</option>
            </select>
          </div>
          <div class="campo">
            <label for="modalidad">Modalidad</label>
            <select id="modalidad" name="modalidad">
              <option value="presencial" <?= $negocio['modalidad'] === 'presencial' ? 'selected' : '' ?>>Presencial</option>
              <option value="virtual" <?= $negocio['modalidad'] === 'virtual' ? 'selected' : '' ?>>Virtual</option>
              <option value="mixta" <?= $negocio['modalidad'] === 'mixta' ? 'selected' : '' ?>>Mixta</option>
            </select>
          </div>
          <div class="campo"><label for="capacidad_alumnos">Capacidad de alumnos</label><input type="number" id="capacidad_alumnos" name="capacidad_alumnos" value="<?= e((string) ($negocio['capacidad_alumnos'] ?? '')) ?>"></div>
          <div class="campo"><label for="alumnos_actuales">Alumnos actuales</label><input type="number" id="alumnos_actuales" name="alumnos_actuales" value="<?= e((string) ($negocio['alumnos_actuales'] ?? '')) ?>"></div>
          <div class="campo"><label for="num_entrenadores">N.° de entrenadores</label><input type="number" id="num_entrenadores" name="num_entrenadores" value="<?= e((string) ($negocio['num_entrenadores'] ?? '')) ?>"></div>
        </details>

        <details>
          <summary>6. Operación</summary>
          <div class="campo">
            <label>Horarios</label>
            <div id="lista-horarios">
              <?php foreach ($horarios as $i => $h): ?>
                <div class="fila-horario">
                  <select name="horario_dia[]">
                    <?php foreach ($diasSemanaNombres as $num => $nombreDia): ?>
                      <option value="<?= $num ?>" <?= (int) $h['dia_semana'] === $num ? 'selected' : '' ?>><?= e($nombreDia) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="horario_turno[]">
                    <?php foreach (['mañana', 'tarde', 'noche'] as $turno): ?>
                      <option value="<?= e($turno) ?>" <?= $h['turno'] === $turno ? 'selected' : '' ?>><?= e(ucfirst($turno)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="time" name="horario_inicio[]" value="<?= e(substr($h['hora_inicio'], 0, 5)) ?>">
                  <input type="time" name="horario_fin[]" value="<?= e(substr($h['hora_fin'], 0, 5)) ?>">
                  <button type="button" class="boton-quitar-fila">✕</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" id="agregar-horario" class="boton boton--secundario boton--pequeno">+ Agregar horario</button>
          </div>
          <div class="campo">
            <label>Rango de precio</label>
            <?php for ($n = 1; $n <= 4; $n++): ?>
              <label><input type="radio" name="rango_precio" value="<?= $n ?>" <?= (int) $negocio['rango_precio'] === $n ? 'checked' : '' ?>> <?= str_repeat('S/ ', $n) ?></label>
            <?php endfor; ?>
          </div>
          <div class="campo"><label for="precio_mensual_ref">Precio mensual referencial (privado, S/)</label><input type="number" step="0.01" id="precio_mensual_ref" name="precio_mensual_ref" value="<?= e((string) ($negocio['precio_mensual_ref'] ?? '')) ?>"></div>
          <label class="opcion-casilla"><input type="checkbox" name="tiene_matricula" <?= $negocio['tiene_matricula'] ? 'checked' : '' ?>> Cobra matrícula</label>
          <label class="opcion-casilla"><input type="checkbox" name="ofrece_beca" <?= $negocio['ofrece_beca'] ? 'checked' : '' ?>> Ofrece becas</label>
          <label class="opcion-casilla"><input type="checkbox" name="clase_prueba_gratis" <?= $negocio['clase_prueba_gratis'] ? 'checked' : '' ?>> Clase de prueba gratis</label>
        </details>

        <details>
          <summary>7. Confianza</summary>
          <label class="opcion-casilla"><input type="checkbox" name="local_propio" <?= $negocio['local_propio'] ? 'checked' : '' ?>> Local propio</label>
          <label class="opcion-casilla"><input type="checkbox" name="seguro_accidentes" <?= $negocio['seguro_accidentes'] ? 'checked' : '' ?>> Seguro contra accidentes</label>
          <label class="opcion-casilla"><input type="checkbox" name="protocolo_salvaguarda" <?= $negocio['protocolo_salvaguarda'] ? 'checked' : '' ?>> Protocolo de salvaguarda infantil</label>
          <label class="opcion-casilla"><input type="checkbox" name="personal_certificado" <?= $negocio['personal_certificado'] ? 'checked' : '' ?>> Personal certificado</label>
          <label class="opcion-casilla"><input type="checkbox" name="requiere_examen_medico" <?= $negocio['requiere_examen_medico'] ? 'checked' : '' ?>> Exige examen médico</label>
          <div class="campo"><label for="afiliacion_federacion">Afiliación a federación</label><input type="text" id="afiliacion_federacion" name="afiliacion_federacion" value="<?= e($negocio['afiliacion_federacion'] ?? '') ?>"></div>
        </details>

        <details>
          <summary>8. Medios</summary>
          <div class="campo">
            <label for="logo">Logo (PNG/JPG/WebP, máx. 2 MB)</label>
            <?php if ($negocio['logo']): ?><img src="/uploads/logos/<?= e($negocio['logo']) ?>" alt="Logo actual" width="80" height="80"><?php endif; ?>
            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
          </div>
          <div class="campo">
            <label>Galería actual (máx. 8 fotos)</label>
            <div class="galeria-actual">
              <?php foreach ($imagenes as $img): ?>
                <div class="galeria-actual__item">
                  <img src="/uploads/galeria/<?= e($img['archivo']) ?>" alt="<?= e($img['alt'] ?? '') ?>" width="120" height="90">
                  <label><input type="checkbox" name="eliminar_imagen[]" value="<?= (int) $img['id'] ?>"> Eliminar</label>
                </div>
              <?php endforeach; ?>
            </div>
            <label for="galeria">Agregar fotos</label>
            <input type="file" id="galeria" name="galeria[]" accept="image/png,image/jpeg,image/webp" multiple>
          </div>
        </details>

        </fieldset>

        <?php if (!$modoLectura): ?>
        <div class="barra-guardado">
          <button type="submit" class="boton boton--primario">Guardar</button>
          <button type="submit" class="boton boton--secundario" onclick="document.getElementById('campo-siguiente').value='nuevo'">Guardar y crear otro</button>
          <button type="submit" class="boton boton--secundario" onclick="document.getElementById('campo-siguiente').value='siguiente_lead'">Guardar y siguiente lead</button>
        </div>
        <?php endif; ?>
      </form>

      <?php if (!$modoLectura): ?>
      <section class="seccion-publicacion">
        <h2>9. Publicación</h2>
        <p>Estado actual: <strong><?= e(str_replace('_', ' ', $negocio['estado'])) ?></strong>
          <?= $negocio['verificado'] ? ' · <span class="insignia insignia--verificada">Verificada</span>' : '' ?></p>
        <ul class="lista-umbral">
          <li class="<?= $publicable ? 'cumple' : 'falta' ?>">Umbral publicable: <?= $publicable ? 'cumplido' : 'incompleto' ?></li>
          <li class="<?= $verificable ? 'cumple' : 'falta' ?>">Umbral verificada: <?= $verificable ? 'cumplido' : 'incompleto' ?></li>
        </ul>
        <form method="post" class="acciones-publicacion">
          <?= csrfCampo() ?>
          <input type="hidden" name="accion" value="cambiar_estado">
          <button type="submit" name="nuevo_estado" value="en_revision" class="boton boton--secundario">Enviar a revisión</button>
          <?php if (adminEsAdministrador()): ?>
            <?php if ($negocio['estado'] !== 'publicado'): ?>
              <button type="submit" name="nuevo_estado" value="publicado" class="boton boton--primario" <?= $publicable ? '' : 'disabled' ?>>Publicar</button>
            <?php else: ?>
              <button type="submit" name="nuevo_estado" value="en_revision" class="boton boton--secundario">Despublicar</button>
            <?php endif; ?>
            <button type="submit" name="nuevo_estado" value="no_contactable" class="boton boton--texto">Marcar no contactable</button>
            <button type="submit" name="nuevo_estado" value="rechazado" class="boton boton--texto">Rechazar</button>
          <?php endif; ?>
        </form>
        <?php if (adminEsAdministrador()): ?>
          <form method="post" class="acciones-publicacion">
            <?= csrfCampo() ?>
            <?php if (!$negocio['verificado']): ?>
              <input type="hidden" name="accion" value="verificar">
              <button type="submit" class="boton boton--primario" <?= $verificable ? '' : 'disabled' ?>>Marcar como verificada</button>
            <?php else: ?>
              <input type="hidden" name="accion" value="quitar_verificacion">
              <button type="submit" class="boton boton--secundario">Quitar verificación</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <div class="campo">
          <form method="post" class="formulario-notas" data-negocio-id="<?= $id ?>">
            <?= csrfCampo() ?>
            <input type="hidden" name="accion" value="guardar_notas">
            <label for="notas_internas">Notas internas</label>
            <textarea id="notas_internas" name="notas_internas" rows="3"><?= e($negocio['notas_internas'] ?? '') ?></textarea>
            <button type="submit" class="boton boton--secundario boton--pequeno">Guardar notas</button>
          </form>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <aside class="editor-negocio__lateral">
      <div class="panel-lead">
        <h2><?= e($negocio['nombre_comercial']) ?></h2>
        <p>Origen: <?= e(str_replace('_', ' ', $negocio['origen'])) ?> · Captado <?= e(tiempoRelativo($negocio['creado_en'])) ?></p>

        <?php if ($negocio['telefono_publico']): ?>
          <div class="acciones-contacto-lateral">
            <a href="tel:+51<?= e($negocio['telefono_publico']) ?>" class="boton boton--primario boton--ancho-completo">📞 Llamar</a>
            <a href="#" id="enlace-whatsapp-plantilla" data-telefono="<?= e($negocio['telefono_publico']) ?>" target="_blank" rel="noopener" class="boton boton--secundario boton--ancho-completo">💬 WhatsApp</a>
            <textarea id="plantilla-whatsapp" rows="3"><?= e($mensajeWhatsApp) ?></textarea>
          </div>
        <?php endif; ?>

        <p><strong>Responsable:</strong>
          <?php
            $nombreAsignado = '—';
            foreach ($admins as $a) {
                if ((int) $a['id'] === (int) $negocio['admin_asignado_id']) { $nombreAsignado = $a['nombre']; }
            }
            echo e($nombreAsignado);
          ?>
        </p>
        <p><strong>Último resultado:</strong> <?= e(str_replace('_', ' ', $negocio['resultado_contacto'])) ?></p>
        <p><strong>Intentos:</strong> <?= (int) $negocio['intentos_contacto'] ?></p>
        <p><strong>Próximo seguimiento:</strong> <?= e($negocio['proximo_seguimiento'] ?? 'sin definir') ?></p>

        <?php if (!$modoLectura): ?>
        <details class="registrar-contacto" open>
          <summary>Registrar contacto</summary>
          <form method="post">
            <?= csrfCampo() ?>
            <input type="hidden" name="accion" value="registrar_contacto">
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
            <textarea name="nota" placeholder="Nota" rows="2"></textarea>
            <button type="submit" class="boton boton--primario boton--ancho-completo">Guardar</button>
          </form>
        </details>
        <?php endif; ?>

        <?php if (adminEsAdministrador() && !$modoLectura): ?>
        <form method="post" class="campo">
          <?= csrfCampo() ?>
          <input type="hidden" name="accion" value="lote_asignar_individual">
          <label for="admin_asignado_id_lateral">Reasignar a</label>
          <select id="admin_asignado_id_lateral" name="admin_asignado_id" onchange="this.form.submit()">
            <option value="">Sin asignar</option>
            <?php foreach ($admins as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= (int) $negocio['admin_asignado_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>

        <h3>Historial</h3>
        <ul class="historial-lead">
          <?php foreach ($historial as $h): ?>
            <li>
              <strong><?= e(str_replace('_', ' ', $h['accion'])) ?></strong>
              <?= $h['resultado'] ? '(' . e($h['resultado']) . ')' : '' ?>
              — <?= e(tiempoRelativo($h['creado_en'])) ?>
              <?= $h['admin_nombre'] ? ' · ' . e($h['admin_nombre']) : '' ?>
              <?php if ($h['nota']): ?><br><small><?= e($h['nota']) ?></small><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p><a href="/admin/negocio-editar.php?id=<?= $id ?>&liberar=1">Salir de la ficha</a></p>
      </div>
    </aside>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>window.UNE_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="/assets/js/admin.js" defer></script>
<?php
require __DIR__ . '/../includes/admin-footer.php';
