</main>

<footer class="pie">
  <div class="contenedor pie__interior">
    <div class="pie__marca">
      <?php if (!isset($logoArchivo)): $logoArchivo = null; foreach (['logo.svg', 'logo.webp', 'logo.png', 'logo.jpg'] as $nombreLogo) { $rutaLogo = RUTA_BASE . '/assets/img/' . $nombreLogo; if (is_file($rutaLogo) && filesize($rutaLogo) > 0) { $logoArchivo = $nombreLogo; break; } } endif; ?>
      <?php if ($logoArchivo): ?>
        <span class="pie__marca-logo">
          <img src="/assets/img/<?= e($logoArchivo) ?>" alt="" height="32">
          <span class="cabecera__marca-texto">UNE SPORTS</span>
        </span>
      <?php else: ?>
        <?php include __DIR__ . '/logo-inline.php'; ?>
      <?php endif; ?>
      <p>El directorio nacional de academias, escuelas y centros de deporte formativo del Perú.</p>

      <?php
        $redesSociales = array_filter([
            'facebook' => SITE_FACEBOOK,
            'instagram' => SITE_INSTAGRAM,
            'tiktok' => SITE_TIKTOK,
            'linkedin' => SITE_LINKEDIN,
            'whatsapp' => SITE_WHATSAPP,
        ]);
      ?>
      <?php if ($redesSociales): ?>
        <nav class="pie__redes" aria-label="Redes sociales de <?= e(SITE_NAME) ?>">
          <?php foreach ($redesSociales as $red => $url): ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($red)) ?>"><?= iconoRedSocial($red) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>

    <nav class="pie__enlaces" aria-label="Enlaces del pie de página">
      <a href="/buscar">Buscar</a>
      <a href="/registrar">Registrar academia</a>
      <a href="/blog">Blog</a>
      <a href="/servicios">Servicios</a>
      <a href="/nosotros">Nosotros</a>
      <a href="/contacto">Contacto</a>
      <a href="/legal-privacidad">Política de privacidad</a>
      <a href="/legal-terminos">Términos y condiciones</a>
    </nav>

    <p class="pie__nota">
      ¿Conoces una academia que no aparece aquí?
      <a href="/sugerir">Sugiérela aquí</a>.
    </p>

    <p class="pie__copy">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Todos los derechos reservados.</p>
  </div>
</footer>

<?php if (SITE_WHATSAPP): ?>
  <?php
    $mensajeWhatsappFlotante = rawurlencode('Hola, quiero más información sobre UNE Sports Perú.');
    $separadorWhatsappFlotante = str_contains(SITE_WHATSAPP, '?') ? '&' : '?';
    $urlWhatsappFlotante = SITE_WHATSAPP . $separadorWhatsappFlotante . 'text=' . $mensajeWhatsappFlotante;
  ?>
  <a href="<?= e($urlWhatsappFlotante) ?>" target="_blank" rel="noopener noreferrer" class="boton-whatsapp-flotante" aria-label="Escríbenos por WhatsApp">
    <?= iconoRedSocial('whatsapp') ?>
  </a>
<?php endif; ?>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
