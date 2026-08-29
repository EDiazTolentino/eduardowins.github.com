</main>

<footer class="pie">
  <div class="contenedor pie__interior">
    <div class="pie__marca">
      <?php if (is_file(RUTA_BASE . '/assets/img/logo.svg') && filesize(RUTA_BASE . '/assets/img/logo.svg') > 0): ?>
        <img src="/assets/img/logo.svg" alt="<?= e(SITE_NAME) ?>" height="32">
      <?php else: ?>
        <?php include __DIR__ . '/logo-inline.php'; ?>
      <?php endif; ?>
      <p>El directorio nacional de academias, escuelas y centros de deporte formativo del Perú.</p>
    </div>

    <nav class="pie__enlaces" aria-label="Enlaces del pie de página">
      <a href="/">Inicio</a>
      <a href="/registrar">Registra tu academia</a>
      <a href="/legal-privacidad">Política de privacidad</a>
    </nav>

    <p class="pie__nota">
      ¿Conoces una academia que no aparece aquí?
      <a href="/registrar">Regístrala gratis</a>.
    </p>

    <p class="pie__copy">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Todos los derechos reservados.</p>
  </div>
</footer>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
