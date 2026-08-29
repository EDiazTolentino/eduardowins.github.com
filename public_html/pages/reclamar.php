<?php
/**
 * pages/reclamar.php — Reclamo de ficha (§6, vía 5). $slug llega desde
 * index.php. El admin revisa el reclamo en /admin/sugerencias.php y,
 * si lo aprueba, entrega el enlace de edición por token (§10 valida
 * los cambios posteriores como en_revision).
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
$valores = ['nombre' => '', 'telefono' => '', 'correo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    foreach ($valores as $campo => $default) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $telefonoNormalizado = normalizarTelefono($valores['telefono']);
    if ($valores['nombre'] === '') {
        $errores[] = 'Ingresa tu nombre.';
    }
    if (!$telefonoNormalizado || !validarTelefonoPeru($telefonoNormalizado)) {
        $errores[] = 'Ingresa un teléfono peruano válido.';
    }
    if ($valores['correo'] !== '' && !filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo ingresado no es válido.';
    }

    if (empty($errores)) {
        $pdo->prepare(
            'INSERT INTO une_reclamos (negocio_id, nombre, telefono, correo) VALUES (:id, :nombre, :telefono, :correo)'
        )->execute([
            ':id' => $negocio['id'], ':nombre' => $valores['nombre'],
            ':telefono' => $telefonoNormalizado, ':correo' => $valores['correo'] ?: null,
        ]);

        enviarCorreoSimple(
            SITE_EMAIL_ADMIN,
            'Reclamo de ficha: ' . $negocio['nombre_comercial'],
            "<p>{$valores['nombre']} ({$telefonoNormalizado}) reclama ser responsable de <strong>{$negocio['nombre_comercial']}</strong>.</p>"
            . '<p><a href="' . SITE_URL . '/admin/sugerencias.php">Revisar en el panel</a></p>'
        );

        $enviado = true;
    }
}

$tituloPagina = 'Reclamar ficha — ' . SITE_NAME;
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta">
  <?php if ($enviado): ?>
    <div class="tarjeta tarjeta--exito">
      <h1>Solicitud recibida</h1>
      <p>Validaremos que seas responsable de <strong><?= e($negocio['nombre_comercial']) ?></strong> y te enviaremos un enlace para que edites tu ficha directamente, sin necesidad de cuenta ni contraseña.</p>
      <p><a href="/negocio/<?= e($slug) ?>">Volver a la ficha</a></p>
    </div>
  <?php else: ?>
    <h1>Reclamar ficha</h1>
    <p class="texto-ayuda">Ficha: <strong><?= e($negocio['nombre_comercial']) ?></strong></p>

    <?php if ($errores): ?>
      <div class="alerta alerta--error" role="alert">
        <ul><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" class="formulario-registro">
      <?= csrfCampo() ?>
      <div class="campo">
        <label for="nombre">Tu nombre *</label>
        <input type="text" id="nombre" name="nombre" required value="<?= e($valores['nombre']) ?>">
      </div>
      <div class="campo">
        <label for="telefono">Tu teléfono *</label>
        <input type="tel" id="telefono" name="telefono" required value="<?= e($valores['telefono']) ?>">
      </div>
      <div class="campo">
        <label for="correo">Tu correo (para enviarte el enlace de edición)</label>
        <input type="email" id="correo" name="correo" value="<?= e($valores['correo']) ?>">
      </div>
      <button type="submit" class="boton boton--primario boton--ancho-completo">Enviar solicitud</button>
    </form>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
