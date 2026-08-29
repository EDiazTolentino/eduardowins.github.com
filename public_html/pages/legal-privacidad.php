<?php
/**
 * pages/legal-privacidad.php — Política de privacidad (Ley N.° 29733).
 */
$tituloPagina = 'Política de privacidad — ' . SITE_NAME;
$metaDescripcion = 'Cómo UNE Sports Perú recopila, usa y protege los datos personales de gestores de academias y visitantes del directorio.';
require __DIR__ . '/../includes/header.php';
?>
<section class="contenedor seccion-angosta seccion-legal">
  <h1>Política de privacidad</h1>
  <p class="texto-ayuda">Última actualización: <?= date('d/m/Y') ?></p>

  <h2>1. Quiénes somos</h2>
  <p><?= e(SITE_NAME) ?> administra un directorio de academias, escuelas y centros de deporte formativo en el Perú. Puedes escribirnos a <?= e(SITE_EMAIL_CONTACTO) ?> para cualquier consulta sobre tus datos.</p>

  <h2>2. Qué datos recogemos y con qué finalidad</h2>
  <table class="tabla-legal">
    <thead><tr><th>Dato</th><th>Finalidad</th></tr></thead>
    <tbody>
      <tr><td>Nombre del negocio y teléfono</td><td>Registrarlo en el directorio y contactarlo para completar su ficha.</td></tr>
      <tr><td>Nombre del representante o gestor</td><td>Uso interno exclusivo, para que nuestro equipo sepa por quién preguntar al llamar. <strong>Nunca se publica.</strong></td></tr>
      <tr><td>Correo electrónico (opcional)</td><td>Enviar el enlace de la ficha publicada y avisos relacionados a tu registro.</td></tr>
      <tr><td>Distrito y deporte principal (opcional)</td><td>Agilizar el contacto y la organización interna del registro.</td></tr>
      <tr><td>Dirección IP</td><td>Prevenir el uso abusivo del formulario (envíos automatizados o repetidos).</td></tr>
    </tbody>
  </table>

  <h2>3. Menores de edad</h2>
  <p>Este directorio no recoge datos personales de niños, niñas ni adolescentes en ningún formulario. Los únicos datos personales que capturamos son los de la persona adulta que gestiona o representa al negocio.</p>

  <h2>4. Fichas publicadas con datos recopilados por nuestro equipo</h2>
  <p>Parte de la información del directorio proviene de fuentes públicas (redes sociales, listados de ligas o municipios) recopilada por nuestro equipo, no siempre con contacto previo del establecimiento. En esos casos:</p>
  <ul>
    <li>Solo publicamos datos de contacto del negocio (nunca del representante).</li>
    <li>La ficha muestra un aviso indicando que la información no ha sido confirmada por el establecimiento.</li>
    <li>Cualquier persona puede solicitar la corrección o el retiro de una ficha desde el enlace disponible en ella; atendemos estas solicitudes en pocos días.</li>
    <li>Si el teléfono publicado resulta ser el celular personal de alguien que solicita su retiro, lo retiramos sin necesidad de justificación adicional.</li>
  </ul>

  <h2>5. Tiempo de conservación</h2>
  <p>Conservamos los datos mientras el negocio esté activo en el directorio o mientras exista una relación de gestión comercial (seguimiento de leads). Puedes solicitar la eliminación de tus datos en cualquier momento.</p>

  <h2>6. Tus derechos (ARCO)</h2>
  <p>De acuerdo con la Ley N.° 29733, puedes ejercer tus derechos de Acceso, Rectificación, Cancelación y Oposición sobre tus datos personales escribiendo a <?= e(SITE_EMAIL_CONTACTO) ?>. Responderemos en un plazo razonable.</p>

  <h2>7. Contacto</h2>
  <p><?= e(SITE_EMAIL_CONTACTO) ?></p>
</section>
<?php
require __DIR__ . '/../includes/footer.php';
