<?php
/**
 * admin/dashboard.php — Pantalla de inicio del backoffice.
 *
 * Nota de alcance: el panel de captación completo (embudo con tasas de
 * conversión, avance por persona del equipo, ranking de cobertura por
 * departamento) es contenido de la Fase 3 (§13: "dashboard de métricas").
 * Esta versión de Fase 1 muestra las cifras básicas para orientar el
 * trabajo diario; se amplía en la Fase 3.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

exigirLoginAdmin();
$pdo = BaseDatos::obtener();

$condicionAsignado = adminEsAdministrador() ? '' : ' AND admin_asignado_id = :admin_id';
$parametros = adminEsAdministrador() ? [] : [':admin_id' => adminId()];

$stmtResumen = $pdo->prepare(
    "SELECT
        SUM(estado = 'lead') AS leads_sin_gestion,
        SUM(estado = 'lead' AND resultado_contacto = 'sin_contactar') AS leads_sin_contactar,
        SUM(proximo_seguimiento IS NOT NULL AND proximo_seguimiento < CURDATE()) AS leads_vencidos,
        SUM(estado = 'publicado') AS fichas_publicadas,
        SUM(estado = 'publicado' AND verificado = 1) AS fichas_verificadas,
        SUM(estado = 'en_revision') AS fichas_en_revision,
        COUNT(*) AS total
     FROM une_negocios WHERE 1=1 {$condicionAsignado}"
);
$stmtResumen->execute($parametros);
$resumen = $stmtResumen->fetch();

$stmtSugerencias = $pdo->query("SELECT COUNT(*) FROM une_sugerencias WHERE procesada = 0");
$sugerenciasPendientes = (int) $stmtSugerencias->fetchColumn();

$tituloPagina = 'Inicio — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <h1>Hola, <?= e($_SESSION['admin_nombre'] ?? '') ?></h1>

  <div class="tarjetas-metricas">
    <a href="/admin/leads.php?vista=sin_contactar" class="tarjeta-metrica">
      <span class="tarjeta-metrica__numero"><?= (int) $resumen['leads_sin_contactar'] ?></span>
      <span class="tarjeta-metrica__etiqueta">Leads sin contactar</span>
    </a>
    <a href="/admin/leads.php?vista=vencidos" class="tarjeta-metrica tarjeta-metrica--alerta">
      <span class="tarjeta-metrica__numero"><?= (int) $resumen['leads_vencidos'] ?></span>
      <span class="tarjeta-metrica__etiqueta">Seguimientos vencidos</span>
    </a>
    <a href="/admin/negocios.php?estado=en_revision" class="tarjeta-metrica">
      <span class="tarjeta-metrica__numero"><?= (int) $resumen['fichas_en_revision'] ?></span>
      <span class="tarjeta-metrica__etiqueta">Fichas en revisión</span>
    </a>
    <a href="/admin/negocios.php?estado=publicado" class="tarjeta-metrica tarjeta-metrica--exito">
      <span class="tarjeta-metrica__numero"><?= (int) $resumen['fichas_publicadas'] ?></span>
      <span class="tarjeta-metrica__etiqueta">Fichas publicadas (<?= (int) $resumen['fichas_verificadas'] ?> verificadas)</span>
    </a>
  </div>

  <?php if (adminEsAdministrador() && $sugerenciasPendientes > 0): ?>
    <p class="alerta alerta--info">Hay <?= $sugerenciasPendientes ?> sugerencia(s) ciudadana(s) pendiente(s) de procesar. <a href="/admin/sugerencias.php">Verlas</a>.</p>
  <?php endif; ?>

  <div class="accesos-rapidos">
    <a href="/admin/leads.php" class="boton boton--primario">Ir a la bandeja de leads</a>
    <a href="/admin/negocios.php" class="boton boton--secundario">Ver todos los negocios</a>
    <a href="/admin/sugerencias.php" class="boton boton--secundario">Sugerencias y reclamos</a>
    <a href="/admin/articulos.php" class="boton boton--secundario">Blog</a>
    <?php if (adminEsAdministrador()): ?>
      <a href="/admin/importar.php" class="boton boton--secundario">Importar CSV</a>
    <?php endif; ?>
  </div>
</div>
<?php
require __DIR__ . '/../includes/admin-footer.php';
