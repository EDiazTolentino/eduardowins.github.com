<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

cerrarSesionAdmin();
header('Location: /admin/index.php');
exit;
