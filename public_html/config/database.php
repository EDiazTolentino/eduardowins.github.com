<?php
/**
 * database.php — Acceso único a la base de datos vía PDO (patrón singleton).
 *
 * Uso: $pdo = BaseDatos::obtener();
 * Siempre usar sentencias preparadas, nunca concatenar valores en el SQL.
 */

require_once __DIR__ . '/config.php';

class BaseDatos
{
    private static ?PDO $instancia = null;

    private function __construct()
    {
    }

    public static function obtener(): PDO
    {
        if (self::$instancia === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instancia = new PDO($dsn, DB_USER, DB_PASS, $opciones);
            } catch (PDOException $e) {
                error_log('Error de conexión a BD: ' . $e->getMessage());
                http_response_code(500);
                if (APP_DEBUG) {
                    die('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
                }
                die('El sitio no está disponible en este momento. Intenta nuevamente en unos minutos.');
            }
        }

        return self::$instancia;
    }

    private function __clone()
    {
    }
}
