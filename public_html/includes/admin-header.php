<?php
/**
 * admin-header.php — Layout del backoffice. Requiere sesión de admin ya
 * verificada (llamar exigirLoginAdmin() antes de incluir este archivo).
 */
$tituloPagina = $tituloPagina ?? 'Panel — ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="es-PE">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($tituloPagina) ?></title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/estilos.css">
</head>
<body class="admin-body">
<header class="admin-cabecera">
  <div class="admin-cabecera__marca">
    <a href="/admin/dashboard.php"><?= e(SITE_NAME) ?> — Panel</a>
  </div>
  <nav class="admin-cabecera__nav">
    <a href="/admin/leads.php">Leads</a>
    <a href="/admin/negocios.php">Negocios</a>
    <?php if (adminEsAdministrador()): ?><a href="/admin/importar.php">Importar CSV</a><?php endif; ?>
    <a href="/admin/dashboard.php">Inicio</a>
  </nav>
  <div class="admin-cabecera__usuario">
    <span><?= e($_SESSION['admin_nombre'] ?? '') ?> · <?= e(adminRol() ?? '') ?></span>
    <a href="/admin/logout.php">Cerrar sesión</a>
  </div>
</header>
<main class="admin-main">
