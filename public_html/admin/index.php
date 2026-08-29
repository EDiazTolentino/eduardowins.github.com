<?php
/**
 * admin/index.php — Login del backoffice.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = BaseDatos::obtener();

if (adminAutenticado()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $resultado = intentarLoginAdmin($pdo, $usuario, $password);
    if ($resultado['ok']) {
        header('Location: /admin/dashboard.php');
        exit;
    }
    $error = $resultado['mensaje'];
}

$tituloPagina = 'Ingresar al panel — ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="es-PE">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($tituloPagina) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="/assets/css/estilos.css">
</head>
<body class="admin-body admin-body--login">
<main class="admin-login">
  <form method="post" class="admin-login__formulario">
    <h1><?= e(SITE_NAME) ?></h1>
    <p class="texto-ayuda">Panel de administración</p>
    <?= csrfCampo() ?>

    <?php if ($error): ?>
      <div class="alerta alerta--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="campo">
      <label for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" required autofocus>
    </div>
    <div class="campo">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="boton boton--primario boton--ancho-completo">Ingresar</button>
  </form>
</main>
</body>
</html>
