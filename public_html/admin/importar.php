<?php
/**
 * admin/importar.php — Carga masiva de leads por CSV (§6, vía 2).
 * Solo nombre_comercial, telefono y contacto_nombre son obligatorios.
 * Flujo: subir → vista previa con validación y detección de duplicados
 * → confirmar. Solo administradores pueden importar.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

exigirLoginAdmin();
exigirRolAdministrador();
$pdo = BaseDatos::obtener();

const COLUMNAS_PLANTILLA = [
    'nombre_comercial', 'telefono', 'contacto_nombre',
    'tipo_registro', 'departamento', 'provincia', 'distrito',
    'email_publico', 'deporte_principal', 'direccion', 'notas',
];

// ---------------------------------------------------------------------
// Descargar plantilla CSV
// ---------------------------------------------------------------------
if (isset($_GET['plantilla'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla-une-sports.csv"');
    $salida = fopen('php://output', 'w');
    fputcsv($salida, COLUMNAS_PLANTILLA, ',', '"', '\\');
    fputcsv($salida, [
        'Academia Deportiva Los Campeones', '987654321', 'María Quispe',
        'formativo', 'Lima', 'Lima', 'Ate', 'contacto@ejemplo.com', 'Fútbol', 'Av. Ejemplo 123', 'Ejemplo de fila',
    ], ',', '"', '\\');
    fclose($salida);
    exit;
}

// ---------------------------------------------------------------------
// Descargar reporte de errores de la última importación
// ---------------------------------------------------------------------
if (isset($_GET['descargar_errores']) && !empty($_SESSION['importar_errores'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="errores-importacion.csv"');
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['fila', 'nombre_comercial', 'telefono', 'motivo'], ',', '"', '\\');
    foreach ($_SESSION['importar_errores'] as $fila) {
        fputcsv($salida, $fila, ',', '"', '\\');
    }
    fclose($salida);
    exit;
}

$mensaje = null;
$preview = null;

function normalizarEncabezado(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    return preg_replace('/[^a-z_]+/', '_', $texto);
}

function resolverUbigeoPorNombre(PDO $pdo, string $departamento, string $provincia, string $distrito): array
{
    if ($distrito === '') {
        return [null, null, null];
    }
    $sql = 'SELECT d.id AS distrito_id, p.id AS provincia_id, dep.id AS departamento_id
            FROM une_distritos d
            JOIN une_provincias p ON p.id = d.provincia_id
            JOIN une_departamentos dep ON dep.id = p.departamento_id
            WHERE d.nombre LIKE :distrito';
    $params = [':distrito' => $distrito];
    if ($departamento !== '') {
        $sql .= ' AND dep.nombre LIKE :departamento';
        $params[':departamento'] = $departamento;
    }
    if ($provincia !== '') {
        $sql .= ' AND p.nombre LIKE :provincia';
        $params[':provincia'] = $provincia;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fila = $stmt->fetch();
    return $fila ? [(int) $fila['departamento_id'], (int) $fila['provincia_id'], (int) $fila['distrito_id']] : [null, null, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'previsualizar' && !empty($_FILES['archivo_csv']['tmp_name'])) {
        $filas = [];
        $handle = fopen($_FILES['archivo_csv']['tmp_name'], 'r');
        $encabezado = null;
        $numeroFila = 1;
        $telefonosEnArchivo = [];

        while (($datos = fgetcsv($handle)) !== false) {
            if ($encabezado === null) {
                $encabezado = array_map('normalizarEncabezado', $datos);
                continue;
            }
            $numeroFila++;
            $fila = array_combine($encabezado, array_pad($datos, count($encabezado), ''));
            $nombre = trim((string) ($fila['nombre_comercial'] ?? ''));
            $telefonoOriginal = trim((string) ($fila['telefono'] ?? ''));
            $contacto = trim((string) ($fila['contacto_nombre'] ?? ''));
            $telefono = $telefonoOriginal !== '' ? normalizarTelefono($telefonoOriginal) : null;

            $estadoFila = 'ok';
            $motivo = '';

            if ($nombre === '' || mb_strlen($nombre) < 3) {
                $estadoFila = 'error';
                $motivo = 'Nombre comercial faltante o muy corto.';
            } elseif (!$telefono || !validarTelefonoPeru($telefono)) {
                $estadoFila = 'error';
                $motivo = 'Teléfono faltante o inválido.';
            } elseif ($contacto === '') {
                $estadoFila = 'error';
                $motivo = 'Falta el nombre del representante.';
            } elseif (isset($telefonosEnArchivo[$telefono])) {
                $estadoFila = 'duplicado';
                $motivo = 'Teléfono repetido en el mismo archivo (fila ' . $telefonosEnArchivo[$telefono] . ').';
            } else {
                $stmtDup = $pdo->prepare('SELECT id FROM une_negocios WHERE telefono_publico = :tel LIMIT 1');
                $stmtDup->execute([':tel' => $telefono]);
                if ($stmtDup->fetchColumn()) {
                    $estadoFila = 'duplicado';
                    $motivo = 'Ya existe un negocio con este teléfono en la base de datos.';
                }
            }

            if ($estadoFila === 'ok') {
                $telefonosEnArchivo[$telefono] = $numeroFila;
            }

            $filas[] = [
                'fila' => $numeroFila,
                'nombre_comercial' => $nombre,
                'telefono' => $telefono,
                'contacto_nombre' => $contacto,
                'tipo_registro' => in_array(trim((string) ($fila['tipo_registro'] ?? '')), ['formativo', 'servicio'], true) ? trim($fila['tipo_registro']) : 'formativo',
                'departamento' => trim((string) ($fila['departamento'] ?? '')),
                'provincia' => trim((string) ($fila['provincia'] ?? '')),
                'distrito' => trim((string) ($fila['distrito'] ?? '')),
                'email_publico' => trim((string) ($fila['email_publico'] ?? '')) ?: null,
                'deporte_principal' => trim((string) ($fila['deporte_principal'] ?? '')) ?: null,
                'direccion' => trim((string) ($fila['direccion'] ?? '')) ?: null,
                'notas' => trim((string) ($fila['notas'] ?? '')) ?: null,
                'estado_fila' => $estadoFila,
                'motivo' => $motivo,
            ];
        }
        fclose($handle);

        $_SESSION['importar_preview'] = $filas;
        $preview = $filas;
    }

    if ($accion === 'confirmar' && !empty($_SESSION['importar_preview'])) {
        $filas = $_SESSION['importar_preview'];
        $insertados = 0;
        $errores = [];

        foreach ($filas as $fila) {
            if ($fila['estado_fila'] !== 'ok') {
                if ($fila['estado_fila'] === 'error') {
                    $errores[] = [$fila['fila'], $fila['nombre_comercial'], $fila['telefono'] ?? '', $fila['motivo']];
                }
                continue;
            }

            [$depId, $provId, $distId] = resolverUbigeoPorNombre($pdo, $fila['departamento'], $fila['provincia'], $fila['distrito']);
            $slug = generarSlugUnico($pdo, $fila['nombre_comercial']);
            $notasFinal = $fila['notas'];
            if ($fila['deporte_principal']) {
                $notasFinal = trim(($notasFinal ? $notasFinal . ' | ' : '') . 'Deporte principal (CSV): ' . $fila['deporte_principal']);
            }
            if ($fila['departamento'] && !$depId) {
                $notasFinal = trim(($notasFinal ? $notasFinal . ' | ' : '') . 'Ubicación del CSV no encontrada en el catálogo: ' . $fila['departamento'] . '/' . $fila['provincia'] . '/' . $fila['distrito']);
            }

            $pdo->prepare(
                'INSERT INTO une_negocios
                    (slug, tipo_registro, nombre_comercial, departamento_id, provincia_id, distrito_id,
                     direccion, telefono_publico, email_publico, contacto_nombre, estado, origen,
                     notas_internas, token_edicion)
                 VALUES
                    (:slug, :tipo, :nombre, :dep, :prov, :dist,
                     :direccion, :tel, :email, :contacto, \'lead\', \'importacion\',
                     :notas, :token)'
            )->execute([
                ':slug' => $slug, ':tipo' => $fila['tipo_registro'], ':nombre' => $fila['nombre_comercial'],
                ':dep' => $depId ?? DEPARTAMENTO_SIN_DEFINIR_ID, ':prov' => $provId, ':dist' => $distId,
                ':direccion' => $fila['direccion'], ':tel' => $fila['telefono'], ':email' => $fila['email_publico'],
                ':contacto' => $fila['contacto_nombre'], ':notas' => $notasFinal ?: null,
                ':token' => generarTokenEdicion(),
            ]);
            $negocioId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, admin_id, accion, nota) VALUES (:id, :admin, \'creado\', \'Importado por CSV.\')')
                ->execute([':id' => $negocioId, ':admin' => adminId()]);
            $insertados++;
        }

        $_SESSION['importar_errores'] = $errores;
        unset($_SESSION['importar_preview']);
        $mensaje = "{$insertados} lead(s) importado(s). " . count($errores) . ' fila(s) con error.';
    }
}

$tituloPagina = 'Importar CSV — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <h1>Importar negocios por CSV</h1>
  <p class="texto-ayuda">Solo <strong>nombre_comercial</strong>, <strong>telefono</strong> y <strong>contacto_nombre</strong> son obligatorios. Las demás columnas son opcionales.</p>
  <p><a href="?plantilla=1" class="boton boton--secundario">Descargar plantilla CSV</a></p>

  <?php if ($mensaje): ?>
    <p class="alerta alerta--exito"><?= e($mensaje) ?></p>
    <?php if (!empty($_SESSION['importar_errores'])): ?>
      <p><a href="?descargar_errores=1">Descargar reporte de errores (<?= count($_SESSION['importar_errores']) ?>)</a></p>
    <?php endif; ?>
    <p><a href="/admin/leads.php">Ir a la bandeja de leads</a></p>
  <?php endif; ?>

  <?php if (!$preview): ?>
    <form method="post" enctype="multipart/form-data">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="previsualizar">
      <div class="campo">
        <label for="archivo_csv">Archivo CSV</label>
        <input type="file" id="archivo_csv" name="archivo_csv" accept=".csv" required>
      </div>
      <button type="submit" class="boton boton--primario">Ver vista previa</button>
    </form>
  <?php else: ?>
    <?php
      $totalOk = count(array_filter($preview, fn ($f) => $f['estado_fila'] === 'ok'));
      $totalError = count(array_filter($preview, fn ($f) => $f['estado_fila'] === 'error'));
      $totalDup = count(array_filter($preview, fn ($f) => $f['estado_fila'] === 'duplicado'));
    ?>
    <h2>Vista previa: <?= count($preview) ?> filas</h2>
    <p><?= $totalOk ?> se importarán · <?= $totalDup ?> duplicadas (se omiten) · <?= $totalError ?> con error (se omiten)</p>
    <div class="tabla-responsiva">
    <table class="tabla-admin">
      <thead><tr><th>Fila</th><th>Nombre</th><th>Teléfono</th><th>Representante</th><th>Estado</th><th>Motivo</th></tr></thead>
      <tbody>
        <?php foreach ($preview as $f): ?>
          <tr class="fila-<?= e($f['estado_fila']) ?>">
            <td><?= (int) $f['fila'] ?></td>
            <td><?= e($f['nombre_comercial']) ?></td>
            <td><?= e($f['telefono'] ?? '') ?></td>
            <td><?= e($f['contacto_nombre']) ?></td>
            <td><?= e($f['estado_fila']) ?></td>
            <td><?= e($f['motivo']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <form method="post">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="confirmar">
      <button type="submit" class="boton boton--primario" <?= $totalOk === 0 ? 'disabled' : '' ?>>Confirmar importación de <?= $totalOk ?> lead(s)</button>
    </form>
  <?php endif; ?>
</div>
<?php
require __DIR__ . '/../includes/admin-footer.php';
