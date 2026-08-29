<?php
/**
 * pages/legal-terminos.php — Términos y condiciones de uso.
 */
$tituloPagina = 'Términos y condiciones — ' . SITE_NAME;
$metaDescripcion = 'Términos y condiciones de uso del directorio nacional de deporte formativo UNE Sports Perú.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta seccion-legal">
  <h1>Términos y condiciones</h1>
  <p class="texto-ayuda">Última actualización: <?= date('d/m/Y') ?></p>

  <h2>1. Qué es UNE Sports Perú</h2>
  <p><?= e(SITE_NAME) ?> es un directorio gratuito de academias, escuelas, clubes y centros de servicio relacionados con el deporte formativo de niños y adolescentes en el Perú. No somos una academia deportiva, no impartimos clases y no somos parte de la relación contractual entre una familia y el establecimiento que elija.</p>

  <h2>2. Naturaleza de la información publicada</h2>
  <p>Algunas fichas son cargadas o confirmadas directamente por el establecimiento; otras son recopiladas por nuestro equipo a partir de fuentes públicas y se muestran con el aviso "Ficha no verificada" hasta que el establecimiento las confirme (ver §7C del proceso interno y nuestra <a href="/legal-privacidad">política de privacidad</a>). No garantizamos que la información de una ficha no verificada sea exacta o esté actualizada.</p>

  <h2>3. Responsabilidad de las familias</h2>
  <p>Recomendamos siempre verificar directamente con cada academia sus horarios, precios, certificaciones y protocolos de seguridad antes de matricular a un menor. La decisión de contratar cualquier servicio listado en el directorio es exclusiva de la familia.</p>

  <h2>4. Responsabilidad de los establecimientos</h2>
  <p>Al registrar o reclamar una ficha, el gestor declara tener la autorización para representar al establecimiento y ser responsable de la veracidad de los datos que confirme o edite.</p>

  <h2>5. Uso del sitio</h2>
  <p>Está prohibido usar los formularios del sitio para enviar información falsa, spam, o contenido que infrinja derechos de terceros. Nos reservamos el derecho de eliminar cualquier ficha, sugerencia o comentario que incumpla esto.</p>

  <h2>6. Propiedad intelectual</h2>
  <p>El nombre, logo y diseño de <?= e(SITE_NAME) ?> son de nuestra propiedad. Las marcas, nombres comerciales y logos de cada academia pertenecen a sus respectivos titulares.</p>

  <h2>7. Cambios en estos términos</h2>
  <p>Podemos actualizar estos términos en cualquier momento; la fecha de última actualización se muestra arriba.</p>

  <h2>8. Ley aplicable</h2>
  <p>Estos términos se rigen por las leyes de la República del Perú.</p>

  <h2>9. Contacto</h2>
  <p><?= e(SITE_EMAIL_CONTACTO) ?></p>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
