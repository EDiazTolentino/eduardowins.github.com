<?php
/**
 * pages/sugerir.php — Sugerencia ciudadana (§6, vía 3). 4 campos: nombre
 * del lugar, distrito, deporte principal, teléfono o red social.
 */

$deportesPrioritarios = [
    2 => 'Fútbol', 9 => 'Vóley', 52 => 'Natación', 7 => 'Básquet',
    1 => 'Artes marciales', 42 => 'Atletismo', 17 => 'Tenis', 84 => 'Gimnasia',
];

$errores = [];
$enviado = false;
$valores = ['nombre_lugar' => '', 'distrito_texto' => '', 'distrito_id' => '', 'departamento_id' => '', 'provincia_id' => '', 'deporte_principal' => '', 'contacto_dato' => '', 'comentario' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    if (!empty($_POST['sitio_web_secundario'])) {
        $enviado = true;
    } else {
        foreach ($valores as $campo => $default) {
            $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        if (mb_strlen($valores['nombre_lugar']) < 3) {
            $errores[] = 'Escribe el nombre del lugar (mínimo 3 caracteres).';
        }

        if (empty($errores)) {
            $distritoId = $valores['distrito_id'] !== '' ? (int) $valores['distrito_id'] : null;
            $deporteId = is_numeric($valores['deporte_principal']) ? (int) $valores['deporte_principal'] : null;
            $comentario = $valores['comentario'];
            if (!is_numeric($valores['deporte_principal']) && $valores['deporte_principal'] !== '') {
                $comentario = trim("Deporte indicado: {$valores['deporte_principal']}. " . $comentario);
            }

            $pdo->prepare(
                'INSERT INTO une_sugerencias (nombre_lugar, distrito_id, deporte_id, contacto_dato, comentario, ip_registro)
                 VALUES (:nombre, :distrito, :deporte, :contacto, :comentario, :ip)'
            )->execute([
                ':nombre' => $valores['nombre_lugar'], ':distrito' => $distritoId, ':deporte' => $deporteId,
                ':contacto' => $valores['contacto_dato'] ?: null, ':comentario' => $comentario ?: null,
                ':ip' => ipBinariaActual(),
            ]);

            enviarCorreoSimple(
                SITE_EMAIL_ADMIN,
                'Nueva sugerencia ciudadana: ' . $valores['nombre_lugar'],
                '<p>Alguien sugirió agregar <strong>' . e($valores['nombre_lugar']) . '</strong> al directorio.</p>'
                . '<p><a href="' . SITE_URL . '/admin/sugerencias.php">Ver sugerencias en el panel</a></p>'
            );

            $enviado = true;
        }
    }
}

$tituloPagina = 'Sugiere una academia — ' . SITE_NAME;
$metaDescripcion = '¿Conoces una academia, escuela o centro de deporte formativo que no está en el directorio? Sugiérela en un minuto.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta">
  <?php if ($enviado): ?>
    <div class="tarjeta tarjeta--exito">
      <h1>¡Gracias por tu sugerencia!</h1>
      <p>Nuestro equipo la revisará y se pondrá en contacto con el lugar para invitarlo a unirse al directorio.</p>
      <p><a href="/buscar">Volver al buscador</a></p>
    </div>
  <?php else: ?>
    <h1>¿Conoces una academia que no aparece aquí?</h1>
    <p class="texto-ayuda">Cuéntanos y nosotros nos encargamos de contactarla.</p>

    <?php if ($errores): ?>
      <div class="alerta alerta--error" role="alert">
        <ul><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" class="formulario-registro" novalidate>
      <?= csrfCampo() ?>
      <input type="text" name="sitio_web_secundario" value="" class="campo-oculto-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="campo">
        <label for="nombre_lugar">Nombre del lugar *</label>
        <input type="text" id="nombre_lugar" name="nombre_lugar" required minlength="3" maxlength="180" value="<?= e($valores['nombre_lugar']) ?>">
      </div>

      <div class="campo">
        <label for="distrito_texto">Distrito</label>
        <input type="text" id="distrito_texto" name="distrito_texto" autocomplete="off" value="<?= e($valores['distrito_texto']) ?>" placeholder="Escribe el distrito">
        <div id="distrito-sugerencias" class="autocompletar-lista" hidden></div>
        <input type="hidden" id="departamento_id" name="departamento_id" value="<?= e($valores['departamento_id']) ?>">
        <input type="hidden" id="provincia_id" name="provincia_id" value="<?= e($valores['provincia_id']) ?>">
        <input type="hidden" id="distrito_id" name="distrito_id" value="<?= e($valores['distrito_id']) ?>">
      </div>

      <div class="campo">
        <label for="deporte_principal">Deporte principal</label>
        <select id="deporte_principal" name="deporte_principal">
          <option value="">Selecciona una opción</option>
          <?php foreach ($deportesPrioritarios as $id => $nombre): ?>
            <option value="<?= (int) $id ?>" <?= (string) $id === $valores['deporte_principal'] ? 'selected' : '' ?>><?= e($nombre) ?></option>
          <?php endforeach; ?>
          <option value="otro" <?= $valores['deporte_principal'] === 'otro' ? 'selected' : '' ?>>Otro</option>
        </select>
      </div>

      <div class="campo">
        <label for="contacto_dato">Teléfono o red social (si la conoces)</label>
        <input type="text" id="contacto_dato" name="contacto_dato" value="<?= e($valores['contacto_dato']) ?>">
      </div>

      <button type="submit" class="boton boton--primario boton--ancho-completo">Enviar sugerencia</button>
    </form>
  <?php endif; ?>
</section>
<script src="/assets/js/formulario.js" defer></script>
<?php
require __DIR__ . '/../includes/footer.php';
