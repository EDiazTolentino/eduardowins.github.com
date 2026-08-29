<?php
/**
 * pages/servicios.php — Páginas informativas de servicios (§8): sin
 * videoconferencia ni pasarela de pago. Solo información + solicitud.
 */

$errores = [];
$enviado = false;
$valores = ['nombre' => '', 'correo' => '', 'servicio' => '', 'mensaje' => ''];

$servicios = [
    'capacitacion' => [
        'nombre' => 'Capacitación',
        'descripcion' => 'Talleres para entrenadores y gestores de academias sobre metodología por edades, prevención de lesiones y salvaguarda infantil en el deporte.',
        'publico' => 'Academias, escuelas y ligas deportivas.',
    ],
    'conferencias' => [
        'nombre' => 'Conferencias',
        'descripcion' => 'Charlas para colegios, municipios y federaciones sobre el rol del deporte formativo en el desarrollo integral de niños y adolescentes.',
        'publico' => 'Colegios, municipios, federaciones deportivas.',
    ],
    'investigacion' => [
        'nombre' => 'Investigación',
        'descripcion' => 'Estudios y reportes sobre el estado del deporte formativo en el Perú, usando los datos agregados del directorio.',
        'publico' => 'Universidades, medios, entidades públicas.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    foreach ($valores as $campo => $default) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if ($valores['nombre'] === '') {
        $errores[] = 'Ingresa tu nombre.';
    }
    if (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresa un correo válido.';
    }
    if (!isset($servicios[$valores['servicio']])) {
        $errores[] = 'Selecciona el servicio que te interesa.';
    }

    if (empty($errores)) {
        $pdo->prepare(
            "INSERT INTO une_solicitudes_contacto (nombre, correo, asunto, mensaje, origen, ip_registro)
             VALUES (:nombre, :correo, :asunto, :mensaje, 'servicios', :ip)"
        )->execute([
            ':nombre' => $valores['nombre'], ':correo' => $valores['correo'],
            ':asunto' => 'Servicio: ' . $servicios[$valores['servicio']]['nombre'],
            ':mensaje' => $valores['mensaje'] ?: null, ':ip' => ipBinariaActual(),
        ]);

        enviarCorreoSimple(
            SITE_EMAIL_ADMIN,
            'Solicitud de información: ' . $servicios[$valores['servicio']]['nombre'],
            '<p>De: ' . e($valores['nombre']) . ' (' . e($valores['correo']) . ')</p><p>' . nl2br(e($valores['mensaje'])) . '</p>'
        );

        $enviado = true;
    }
}

$tituloPagina = 'Servicios — ' . SITE_NAME;
$metaDescripcion = 'Capacitación, conferencias e investigación sobre deporte formativo, para academias, colegios y entidades públicas.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta">
  <h1>Servicios</h1>
  <p class="texto-ayuda">Además del directorio, ofrecemos estos servicios relacionados con el deporte formativo.</p>

  <div class="grid-servicios">
    <?php foreach ($servicios as $clave => $s): ?>
      <div class="tarjeta-servicio" id="<?= e($clave) ?>">
        <h2><?= e($s['nombre']) ?></h2>
        <p><?= e($s['descripcion']) ?></p>
        <p class="texto-ayuda"><strong>Público objetivo:</strong> <?= e($s['publico']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>Solicita información</h2>
  <?php if ($enviado): ?>
    <div class="tarjeta tarjeta--exito"><p>Gracias por tu interés. Te contactaremos pronto.</p></div>
  <?php else: ?>
    <?php if ($errores): ?>
      <div class="alerta alerta--error" role="alert">
        <ul><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <form method="post" class="formulario-registro">
      <?= csrfCampo() ?>
      <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" required value="<?= e($valores['nombre']) ?>"></div>
      <div class="campo"><label for="correo">Correo *</label><input type="email" id="correo" name="correo" required value="<?= e($valores['correo']) ?>"></div>
      <div class="campo">
        <label for="servicio">Servicio *</label>
        <select id="servicio" name="servicio" required>
          <option value="">Selecciona</option>
          <?php foreach ($servicios as $clave => $s): ?>
            <option value="<?= e($clave) ?>" <?= $valores['servicio'] === $clave ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo"><label for="mensaje">Cuéntanos más</label><textarea id="mensaje" name="mensaje" rows="4"><?= e($valores['mensaje']) ?></textarea></div>
      <button type="submit" class="boton boton--primario boton--ancho-completo">Enviar solicitud</button>
    </form>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
