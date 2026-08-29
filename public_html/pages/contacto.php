<?php
/**
 * pages/contacto.php — Formulario de contacto general.
 */

$errores = [];
$enviado = false;
$valores = ['nombre' => '', 'correo' => '', 'asunto' => '', 'mensaje' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    if (!empty($_POST['sitio_web_secundario'])) {
        $enviado = true;
    } else {
        foreach ($valores as $campo => $default) {
            $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        if ($valores['nombre'] === '') {
            $errores[] = 'Ingresa tu nombre.';
        }
        if (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresa un correo válido.';
        }
        if (mb_strlen($valores['mensaje']) < 5) {
            $errores[] = 'Escribe tu mensaje.';
        }

        if (empty($errores)) {
            $pdo->prepare(
                "INSERT INTO une_solicitudes_contacto (nombre, correo, asunto, mensaje, origen, ip_registro)
                 VALUES (:nombre, :correo, :asunto, :mensaje, 'contacto', :ip)"
            )->execute([
                ':nombre' => $valores['nombre'], ':correo' => $valores['correo'],
                ':asunto' => $valores['asunto'] ?: null, ':mensaje' => $valores['mensaje'],
                ':ip' => ipBinariaActual(),
            ]);

            enviarCorreoSimple(
                SITE_EMAIL_ADMIN,
                'Nuevo mensaje de contacto: ' . ($valores['asunto'] ?: 'Sin asunto'),
                '<p>De: ' . e($valores['nombre']) . ' (' . e($valores['correo']) . ')</p><p>' . nl2br(e($valores['mensaje'])) . '</p>'
            );

            $enviado = true;
        }
    }
}

$tituloPagina = 'Contacto — ' . SITE_NAME;
$metaDescripcion = 'Escríbenos si tienes dudas, sugerencias o quieres saber más sobre UNE Sports Perú.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta">
  <h1>Contacto</h1>

  <?php if ($enviado): ?>
    <div class="tarjeta tarjeta--exito">
      <h2>¡Gracias por escribirnos!</h2>
      <p>Te responderemos a la brevedad.</p>
    </div>
  <?php else: ?>
    <?php if ($errores): ?>
      <div class="alerta alerta--error" role="alert">
        <ul><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" class="formulario-registro" novalidate>
      <?= csrfCampo() ?>
      <input type="text" name="sitio_web_secundario" value="" class="campo-oculto-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" required value="<?= e($valores['nombre']) ?>"></div>
      <div class="campo"><label for="correo">Correo *</label><input type="email" id="correo" name="correo" required value="<?= e($valores['correo']) ?>"></div>
      <div class="campo"><label for="asunto">Asunto</label><input type="text" id="asunto" name="asunto" value="<?= e($valores['asunto']) ?>"></div>
      <div class="campo"><label for="mensaje">Mensaje *</label><textarea id="mensaje" name="mensaje" rows="5" required><?= e($valores['mensaje']) ?></textarea></div>

      <button type="submit" class="boton boton--primario boton--ancho-completo">Enviar mensaje</button>
    </form>
  <?php endif; ?>

  <div class="datos-contacto">
    <h2>Otros datos de contacto</h2>
    <p>Correo: <a href="mailto:<?= e(SITE_EMAIL_CONTACTO) ?>"><?= e(SITE_EMAIL_CONTACTO) ?></a></p>
  </div>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
