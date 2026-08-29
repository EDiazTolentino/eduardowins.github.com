<?php
/**
 * pages/solicitar-retiro.php — "Solicitar corrección o retiro de esta
 * ficha" (§10, salvaguarda legal obligatoria para fichas no verificadas).
 * $slug llega desde index.php.
 */

$stmt = $pdo->prepare("SELECT id, nombre_comercial FROM une_negocios WHERE slug = :slug AND estado = 'publicado'");
$stmt->execute([':slug' => $slug]);
$negocio = $stmt->fetch();

if (!$negocio) {
    http_response_code(404);
    $tituloPagina = 'Ficha no encontrada — ' . SITE_NAME;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="contenedor seccion-error-404"><h1>No encontramos esta ficha</h1></section>';
    require __DIR__ . '/../includes/footer.php';
    return;
}

$errores = [];
$enviado = false;
$valores = ['nombre' => '', 'correo' => '', 'motivo' => '', 'mensaje' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    foreach ($valores as $campo => $default) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $motivosValidos = ['corregir_datos', 'retirar_ficha', 'no_soy_responsable', 'otro'];

    if ($valores['nombre'] === '') {
        $errores[] = 'Ingresa tu nombre.';
    }
    if (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresa un correo válido para poder responderte.';
    }
    if (!in_array($valores['motivo'], $motivosValidos, true)) {
        $errores[] = 'Selecciona el motivo de tu solicitud.';
    }

    if (empty($errores)) {
        $pdo->prepare(
            'INSERT INTO une_solicitudes_retiro (negocio_id, nombre, correo, motivo, mensaje)
             VALUES (:negocio_id, :nombre, :correo, :motivo, :mensaje)'
        )->execute([
            ':negocio_id' => $negocio['id'], ':nombre' => $valores['nombre'], ':correo' => $valores['correo'],
            ':motivo' => $valores['motivo'], ':mensaje' => $valores['mensaje'] ?: null,
        ]);

        enviarCorreoSimple(
            SITE_EMAIL_ADMIN,
            'Solicitud de corrección/retiro: ' . $negocio['nombre_comercial'],
            "<p>{$valores['nombre']} ({$valores['correo']}) solicitó <strong>{$valores['motivo']}</strong> para la ficha "
            . "<strong>{$negocio['nombre_comercial']}</strong>.</p><p>" . nl2br(e($valores['mensaje'])) . '</p>'
        );

        $enviado = true;
    }
}

$tituloPagina = 'Solicitar corrección o retiro — ' . SITE_NAME;
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta">
  <?php if ($enviado): ?>
    <div class="tarjeta tarjeta--exito">
      <h1>Solicitud recibida</h1>
      <p>Revisaremos la ficha de <strong><?= e($negocio['nombre_comercial']) ?></strong> en los próximos días y te responderemos a tu correo.</p>
      <p><a href="/negocio/<?= e($slug) ?>">Volver a la ficha</a></p>
    </div>
  <?php else: ?>
    <h1>Solicitar corrección o retiro de ficha</h1>
    <p class="texto-ayuda">Ficha: <strong><?= e($negocio['nombre_comercial']) ?></strong></p>

    <?php if ($errores): ?>
      <div class="alerta alerta--error" role="alert">
        <ul><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" class="formulario">
      <?= csrfCampo() ?>
      <div class="campo">
        <label for="nombre">Tu nombre *</label>
        <input type="text" id="nombre" name="nombre" required value="<?= e($valores['nombre']) ?>">
      </div>
      <div class="campo">
        <label for="correo">Tu correo *</label>
        <input type="email" id="correo" name="correo" required value="<?= e($valores['correo']) ?>">
      </div>
      <div class="campo">
        <label for="motivo">Motivo *</label>
        <select id="motivo" name="motivo" required>
          <option value="">Selecciona una opción</option>
          <option value="corregir_datos" <?= $valores['motivo'] === 'corregir_datos' ? 'selected' : '' ?>>Corregir datos incorrectos</option>
          <option value="retirar_ficha" <?= $valores['motivo'] === 'retirar_ficha' ? 'selected' : '' ?>>Retirar esta ficha</option>
          <option value="no_soy_responsable" <?= $valores['motivo'] === 'no_soy_responsable' ? 'selected' : '' ?>>No soy responsable de este negocio</option>
          <option value="otro" <?= $valores['motivo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
        </select>
      </div>
      <div class="campo">
        <label for="mensaje">Cuéntanos más (opcional)</label>
        <textarea id="mensaje" name="mensaje" rows="4"><?= e($valores['mensaje']) ?></textarea>
      </div>
      <button type="submit" class="boton boton--primario">Enviar solicitud</button>
    </form>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
