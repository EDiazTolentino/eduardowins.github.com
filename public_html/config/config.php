<?php
/**
 * config.php — Constantes de configuración del sitio.
 *
 * IMPORTANTE: este archivo contiene credenciales. No debe versionarse en un
 * repositorio público. En el hosting, súbelo dentro de config/ y verifica
 * que .htaccess bloquee el acceso directo a esta carpeta (ver public_html/.htaccess).
 */

// ---------------------------------------------------------------------
// Base de datos (ajusta con los datos reales de tu panel de Hostinger)
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'u000000000_une_sports');
define('DB_USER', 'u000000000_une_admin');
define('DB_PASS', 'CAMBIA_ESTA_CONTRASENA');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Sitio
// ---------------------------------------------------------------------
define('SITE_NAME', 'UNE Sports Perú');
define('SITE_URL', 'https://unesportsperu.pe'); // sin slash final
define('SITE_EMAIL_CONTACTO', 'contacto@unesportsperu.pe');
define('SITE_EMAIL_ADMIN', 'registro@unesportsperu.pe'); // recibe avisos de nuevos leads
define('WHATSAPP_PREFIJO_PAIS', '51');
// Departamento sentinela "Sin definir" (sql/03_ubigeo.sql), usado cuando
// un lead del formulario público llega sin distrito resuelto.
define('DEPARTAMENTO_SIN_DEFINIR_ID', 26);

// ---------------------------------------------------------------------
// SMTP (PHPMailer) — datos del correo del plan Business Starter
// ---------------------------------------------------------------------
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SEGURIDAD', 'ssl'); // ssl | tls
define('SMTP_USUARIO', 'registro@unesportsperu.pe');
define('SMTP_PASSWORD', 'CAMBIA_ESTA_CONTRASENA');
define('SMTP_NOMBRE_REMITENTE', 'UNE Sports Perú');

// ---------------------------------------------------------------------
// Seguridad
// ---------------------------------------------------------------------
// Genera una cadena aleatoria propia antes de subir a producción, p. ej.
// con: php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET', 'CAMBIA_ESTA_CLAVE_ALEATORIA_LARGA');
// Token para invocar tareas periódicas (sitemap, recordatorios) sin cron,
// vía un servicio externo de ping (p. ej. cron-job.org). Cambiar en producción.
define('TAREAS_TOKEN', 'CAMBIA_ESTE_TOKEN_LARGO');

// ---------------------------------------------------------------------
// Rutas absolutas del servidor
// ---------------------------------------------------------------------
define('RUTA_BASE', dirname(__DIR__));
define('RUTA_UPLOADS', RUTA_BASE . '/uploads');
define('URL_UPLOADS', '/uploads');

// ---------------------------------------------------------------------
// Entorno
// ---------------------------------------------------------------------
define('APP_DEBUG', false); // true solo en desarrollo local, nunca en producción

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// UTC en todo el backend (coherente con `SET time_zone = "+00:00"` en las
// migraciones SQL): así NOW() de MySQL y time()/strtotime() de PHP miden
// la misma hora absoluta. Perú no usa horario de verano, así que restar
// 5 horas a un DATETIME de BD basta para mostrarlo en hora de Lima cuando
// haga falta (ver formatoFechaLima() en includes/functions.php).
date_default_timezone_set('UTC');
