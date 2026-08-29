<?php
/**
 * pages/registrar.php — Captura rápida pública (§7A).
 * Una sola pantalla, 3 campos obligatorios + 3 opcionales. Sin cuenta,
 * sin pasos, sin subida de archivos. Genera un LEAD, no una ficha.
 */

// Mapa fijo de las 8 disciplinas más frecuentes (ids de une_deportes,
// ver sql/02_catalogos.sql) + 2 sentinelas de triaje.
$deportesPrioritarios = [
    2  => 'Fútbol',
    9  => 'Vóley',
    52 => 'Natación',
    7  => 'Básquet',
    1  => 'Artes marciales',
    42 => 'Atletismo',
    17 => 'Tenis',
    84 => 'Gimnasia',
];

$errores = [];
$enviado = false;
$valores = [
    'nombre_comercial' => '',
    'telefono' => '',
    'contacto_nombre' => '',
    'distrito_texto' => '',
    'departamento_id' => '',
    'provincia_id' => '',
    'distrito_id' => '',
    'email_publico' => '',
    'deporte_principal' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();

    // Honeypot: si un bot llenó este campo invisible, fingimos éxito.
    if (!empty($_POST['sitio_web_secundario'])) {
        $enviado = true;
    } else {
        foreach ($valores as $campo => $default) {
            $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        // Límite de 3 envíos nuevos por IP por hora.
        $ipBin = ipBinariaActual();
        if ($ipBin !== null) {
            $stmtRate = $pdo->prepare(
                'SELECT COUNT(*) FROM une_negocios WHERE ip_registro = :ip AND creado_en > (NOW() - INTERVAL 1 HOUR)'
            );
            $stmtRate->execute([':ip' => $ipBin]);
            if ((int) $stmtRate->fetchColumn() >= 3) {
                $errores[] = 'Has enviado varios registros en poco tiempo. Espera unos minutos e inténtalo de nuevo.';
            }
        }

        if (mb_strlen($valores['nombre_comercial']) < 3 || mb_strlen($valores['nombre_comercial']) > 180) {
            $errores[] = 'Escribe el nombre de la academia, escuela o centro (mínimo 3 caracteres).';
        }

        $telefonoNormalizado = normalizarTelefono($valores['telefono']);
        if (!$telefonoNormalizado || !validarTelefonoPeru($telefonoNormalizado)) {
            $errores[] = 'Ingresa un teléfono o celular peruano válido.';
        }

        if ($valores['contacto_nombre'] === '') {
            $errores[] = 'Ingresa el nombre de la persona a la que podemos preguntar por teléfono.';
        }

        if ($valores['email_publico'] !== '' && !filter_var($valores['email_publico'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo ingresado no es válido.';
        }

        if (empty($_POST['consentimiento'])) {
            $errores[] = 'Debes aceptar la política de privacidad para continuar.';
        }

        if (empty($errores)) {
            $departamentoId = $valores['departamento_id'] !== '' ? (int) $valores['departamento_id'] : null;
            $provinciaId = $valores['provincia_id'] !== '' ? (int) $valores['provincia_id'] : null;
            $distritoId = $valores['distrito_id'] !== '' ? (int) $valores['distrito_id'] : null;
            $emailPublico = $valores['email_publico'] !== '' ? $valores['email_publico'] : null;
            $deportePrincipal = $valores['deporte_principal'];

            $stmtExiste = $pdo->prepare('SELECT id FROM une_negocios WHERE telefono_publico = :tel LIMIT 1');
            $stmtExiste->execute([':tel' => $telefonoNormalizado]);
            $existenteId = $stmtExiste->fetchColumn();

            if ($existenteId) {
                $negocioId = (int) $existenteId;
                // departamento_id no admite NULL: cuando quedó sin resolver se
                // guardó con el sentinela "Sin definir", así que aquí se
                // reemplaza solo si todavía tiene ese valor (no con COALESCE).
                $pdo->prepare(
                    'UPDATE une_negocios SET
                        departamento_id = COALESCE(NULLIF(departamento_id, :sindefinir), :dep, departamento_id),
                        provincia_id = COALESCE(provincia_id, :prov),
                        distrito_id = COALESCE(distrito_id, :dist),
                        email_publico = COALESCE(email_publico, :email),
                        contacto_nombre = COALESCE(contacto_nombre, :contacto)
                     WHERE id = :id'
                )->execute([
                    ':sindefinir' => DEPARTAMENTO_SIN_DEFINIR_ID,
                    ':dep' => $departamentoId, ':prov' => $provinciaId, ':dist' => $distritoId,
                    ':email' => $emailPublico, ':contacto' => $valores['contacto_nombre'],
                    ':id' => $negocioId,
                ]);
                $accionHistorial = 'ficha_actualizada';
                $notaHistorial = 'Reenvío del formulario público de captura rápida (mismo teléfono).';
            } else {
                $departamentoId = $departamentoId ?? DEPARTAMENTO_SIN_DEFINIR_ID; // el equipo lo corrige al llamar al lead.
                $slug = generarSlugUnico($pdo, $valores['nombre_comercial']);
                $tokenEdicion = generarTokenEdicion();
                $tipoRegistro = $deportePrincipal === 'servicio' ? 'servicio' : 'formativo';
                $notasInternas = null;
                if ($deportePrincipal === 'otro') {
                    $notasInternas = 'Deporte principal indicado como "Otro" en el formulario público.';
                } elseif ($deportePrincipal === 'servicio') {
                    $notasInternas = 'Marcado como centro de servicio (no deportivo) en el formulario público.';
                }

                $pdo->prepare(
                    'INSERT INTO une_negocios
                        (slug, tipo_registro, nombre_comercial, departamento_id, provincia_id, distrito_id,
                         telefono_publico, email_publico, contacto_nombre, estado, origen,
                         notas_internas, token_edicion, utm_source, utm_campaign, ip_registro)
                     VALUES
                        (:slug, :tipo, :nombre, :dep, :prov, :dist,
                         :tel, :email, :contacto, \'lead\', \'captura_rapida\',
                         :notas, :token, :utm_source, :utm_campaign, :ip)'
                )->execute([
                    ':slug' => $slug, ':tipo' => $tipoRegistro, ':nombre' => $valores['nombre_comercial'],
                    ':dep' => $departamentoId, ':prov' => $provinciaId, ':dist' => $distritoId,
                    ':tel' => $telefonoNormalizado, ':email' => $emailPublico, ':contacto' => $valores['contacto_nombre'],
                    ':notas' => $notasInternas, ':token' => $tokenEdicion,
                    ':utm_source' => $_POST['utm_source'] ?: null, ':utm_campaign' => $_POST['utm_campaign'] ?: null,
                    ':ip' => $ipBin,
                ]);
                $negocioId = (int) $pdo->lastInsertId();
                $accionHistorial = 'creado';
                $notaHistorial = 'Lead capturado desde el formulario público /registrar.';
            }

            if (is_numeric($deportePrincipal)) {
                $pdo->prepare('INSERT IGNORE INTO une_negocio_deportes (negocio_id, deporte_id) VALUES (:id, :dep)')
                    ->execute([':id' => $negocioId, ':dep' => (int) $deportePrincipal]);
            }

            calcularCompletitud($pdo, $negocioId);

            $pdo->prepare('INSERT INTO une_lead_historial (negocio_id, accion, nota) VALUES (:id, :accion, :nota)')
                ->execute([':id' => $negocioId, ':accion' => $accionHistorial, ':nota' => $notaHistorial]);

            registrarEvento($pdo, 'registro_enviado', $negocioId, [
                'distrito_completado' => $distritoId !== null,
                'email_completado' => $emailPublico !== null,
                'deporte_completado' => $deportePrincipal !== '',
            ]);

            $urlAdmin = SITE_URL . '/admin/negocio-editar.php?id=' . $negocioId;
            enviarCorreoSimple(
                SITE_EMAIL_ADMIN,
                'Nuevo lead: ' . $valores['nombre_comercial'],
                "<p><strong>{$valores['nombre_comercial']}</strong> se acaba de registrar en UNE Sports Perú.</p>"
                . "<p>Teléfono: {$telefonoNormalizado}<br>Representante: " . e($valores['contacto_nombre']) . "</p>"
                . "<p><a href=\"{$urlAdmin}\">Abrir ficha en el panel</a></p>"
            );

            $enviado = true;
        }
    }
}
?>
<?php
$tituloPagina = 'Registra tu academia gratis — ' . SITE_NAME;
$metaDescripcion = 'Regístrate en menos de 30 segundos. Nuestro equipo te contacta para completar y publicar tu ficha gratis en el directorio nacional de deporte formativo.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta">

<?php if ($enviado): ?>
  <div class="tarjeta tarjeta--exito">
    <h1>¡Listo! Recibimos tus datos</h1>
    <p>Nuestro equipo te contactará en las próximas 48 horas para completar el perfil de tu academia y publicarlo gratis en el directorio.</p>
    <p>
      <button type="button" class="boton boton--secundario" id="boton-compartir" data-url="<?= e(SITE_URL . '/registrar') ?>">
        Compartir este formulario con otra academia
      </button>
    </p>
    <p><a href="/">Volver al inicio</a></p>
  </div>
<?php else: ?>

  <h1>Registra tu academia, escuela o centro</h1>
  <p class="texto-ayuda">Menos de 30 segundos. Sin cuenta, sin contraseña. Nuestro equipo te llama para completar y publicar tu ficha gratis.</p>

  <?php if ($errores): ?>
    <div class="alerta alerta--error" role="alert">
      <ul>
        <?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="/registrar" class="formulario-registro" novalidate>
    <?= csrfCampo() ?>
    <input type="text" name="sitio_web_secundario" value="" class="campo-oculto-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
    <input type="hidden" name="utm_source" value="<?= e($_GET['utm_source'] ?? '') ?>">
    <input type="hidden" name="utm_campaign" value="<?= e($_GET['utm_campaign'] ?? '') ?>">

    <div class="campo">
      <label for="nombre_comercial">Nombre de la academia, escuela o centro *</label>
      <input type="text" id="nombre_comercial" name="nombre_comercial" required minlength="3" maxlength="180"
             value="<?= e($valores['nombre_comercial']) ?>" placeholder="Ej. Academia Deportiva Los Campeones">
    </div>

    <div class="campo">
      <label for="telefono">Teléfono o celular *</label>
      <input type="tel" id="telefono" name="telefono" required inputmode="numeric"
             value="<?= e($valores['telefono']) ?>" placeholder="Ej. 987654321">
    </div>

    <div class="campo">
      <label for="contacto_nombre">Nombre de la persona a quien podemos llamar *</label>
      <input type="text" id="contacto_nombre" name="contacto_nombre" required maxlength="120"
             value="<?= e($valores['contacto_nombre']) ?>" placeholder="Ej. María Quispe">
      <p class="campo__nota">Este dato es solo para que nuestro equipo sepa por quién preguntar. No se mostrará en el sitio.</p>
    </div>

    <hr class="separador">
    <p class="separador__etiqueta">Opcional — nos ayuda a contactarte más rápido</p>

    <div class="campo">
      <label for="distrito_texto">Distrito</label>
      <input type="text" id="distrito_texto" name="distrito_texto" autocomplete="off"
             value="<?= e($valores['distrito_texto']) ?>" placeholder="Escribe tu distrito, ej. Ate">
      <div id="distrito-sugerencias" class="autocompletar-lista" hidden></div>
      <input type="hidden" id="departamento_id" name="departamento_id" value="<?= e($valores['departamento_id']) ?>">
      <input type="hidden" id="provincia_id" name="provincia_id" value="<?= e($valores['provincia_id']) ?>">
      <input type="hidden" id="distrito_id" name="distrito_id" value="<?= e($valores['distrito_id']) ?>">
    </div>

    <div class="campo">
      <label for="email_publico">Correo electrónico</label>
      <input type="email" id="email_publico" name="email_publico" value="<?= e($valores['email_publico']) ?>" placeholder="tucorreo@ejemplo.com">
    </div>

    <div class="campo">
      <label for="deporte_principal">Deporte principal</label>
      <select id="deporte_principal" name="deporte_principal">
        <option value="">Selecciona una opción</option>
        <?php foreach ($deportesPrioritarios as $id => $nombre): ?>
          <option value="<?= (int) $id ?>" <?= (string) $id === $valores['deporte_principal'] ? 'selected' : '' ?>><?= e($nombre) ?></option>
        <?php endforeach; ?>
        <option value="otro" <?= $valores['deporte_principal'] === 'otro' ? 'selected' : '' ?>>Otro</option>
        <option value="servicio" <?= $valores['deporte_principal'] === 'servicio' ? 'selected' : '' ?>>Centro de servicio (no deportivo)</option>
      </select>
    </div>

    <div class="campo campo--checkbox">
      <label>
        <input type="checkbox" name="consentimiento" value="1">
        Acepto que mis datos sean usados para contactarme, según la
        <a href="/legal-privacidad" target="_blank" rel="noopener">política de privacidad</a>.
      </label>
    </div>

    <button type="submit" class="boton boton--primario boton--ancho-completo">Enviar registro</button>
  </form>

<?php endif; ?>
</section>
<script src="/assets/js/formulario.js" defer></script>
<?php
require __DIR__ . '/../includes/footer.php';
